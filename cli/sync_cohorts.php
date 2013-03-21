<?php


// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * cohort sync with LDAP groups script.
 *
 * This script is meant to be called from a cronjob to sync moodle's cohorts
 * registered in LDAP groups where the CAS/LDAP backend acts as 'master'.
 *
 * Sample cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/auth/cas/cli/sync_cohorts.php
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d memory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 *
 *THis script should be run some time after /var/www/moodle/auth/cas/cli/sync_users.php
 *
 * Performance notes:
 * We have optimized it as best as we could for PostgreSQL and MySQL, with 27K students
 * we have seen this take 10 minutes.
 *
 * @package    auth
 * @subpackage CAS 
 * @copyright  2010 Patrick Pollet - based on code by Jeremy Guittirez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * CONFIGURATION
 * this script make use of current Moodle's LDAP/CAS settings 
 * user_attribute 
 * member_attribute
 * member_attribute_isdn
 * objectclass
 * 
 * and the following default values that can be altered in Moodle's
 * config.php file
 * $CFG->ldap_group_attribute='cn';          // in case your groups are not cn=xxx,
 * $CFG->ldap_group_class='groupofuniquenames'; // in case your groups are of class group ...
 * $CFG->ldap_real_user_attribute='uid';     // in case your user_attribute is in mixed case in LDAP (sAMAccountName) 
 * $CFG->ldap_process_nested_groups=0;       // turn on nested groups
 * $CFG->debug_ldap_groupes=false;           // turn on extensive debug upon running
 * $CFG->cohort_synching_ldap_groups_autocreate_cohorts // if false will not create missing cohorts (admin must create them before) 
 * 
 * 
 */

/**
 * REVISIONS
 * 1.0 initial release see
 * 1.1 1 Nov 2012 added support for the case when group member attribute IS NOT of
 * the form xx=moodleusername,ou=yyyy,ou=zzzz that seems to be more commun than I thought ...
 * i.e. in AD it seems to be cn=user fullname, ou=yyyy,ou=zzzz
 * 1.2 16 Nov 2012 added support for nested groups
 * 1.2.1 18 Nov 2012 improved for LDAP directories with mixed case attributes names (i.e. sAMMAccountName)
 *                   and values (i.e. username=JDoe)
 * 1.3   07 March 2013 also work when authentication is LDAP and not CAS                   
 */

define('CLI_SCRIPT', true);

require (dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once ($CFG->dirroot . '/group/lib.php');
require_once ($CFG->dirroot . '/cohort/lib.php');

require_once ($CFG->dirroot . '/auth/ldap/auth.php');

/**
 * CAS authentication plugin.
 * extended to fetch LDAP groups and to be cohort aware
 */
class auth_plugin_cohort extends auth_plugin_ldap {

    /**
     * avoid infinite loop with nested groups in 'funny' directories
     * @var array
     */
    var $anti_recursion_array;


    /**
     * Constructor.
     */

    function auth_plugin_cohort() {
        global $CFG;
        // revision March 2013 needed to fetch the proper LDAP parameters
        // host, context ... from table config_plugins see comments in https://tracker.moodle.org/browse/MDL-25011
        if (is_enabled_auth('cas')) {
            $this->authtype = 'cas';
            $this->roleauth = 'auth_cas';
            $this->errorlogtag = '[AUTH CAS] ';
        } else if (is_enabled_auth('ldap')){ 
            $this->authtype = 'ldap';
            $this->roleauth = 'auth_ldap';
            $this->errorlogtag = '[AUTH LDAP] '; 
        } else {
            error_log('[SYNCH COHORTS] ' . get_string('pluginnotenabled', 'auth_ldap'));
            die;
        }
        
        $this->init_plugin($this->authtype);
        //TODO must be in some setting screen Currently in config.php
        $this->config->group_attribute = !empty($CFG->ldap_group_attribute)?$CFG->ldap_group_attribute:'cn';
        $this->config->group_class = !empty($CFG->ldap_group_class )?$CFG->ldap_group_class :'groupOfUniqueNames';
        $this->config->process_nested_groups=!empty($CFG->ldap_process_nested_groups )?$CFG->ldap_process_nested_groups :false;
        /**
         * cache for found groups dn
         * used for nested groups processing
         */
        $this->config->groups_dn_cache=array();
        $this->anti_recursion_array=array();
         
        /** Moodle DO convert to lowercase all LDAP attributes in setting screens
         * this cause an issue when searching LDAP group members when user's naming attribute
         * is in mixed case in the LDAP , such as sAMAccountName instead of samaccountname
         * If your cohorts are not populated by this script try setting this value in config.php
         */
        if (!empty($CFG->ldap_real_user_attribute)) {
            if ($CFG->debug_ldap_groupes){
                pp_print_object("using {$CFG->ldap_real_user_attribute} as naming attribute instead of {$this->config->user_attribute}",'');
            }
            $this->config->user_attribute= $CFG->ldap_real_user_attribute;
        }
        if ($CFG->debug_ldap_groupes){
            pp_print_object('plugin config',$this->config);
        }

    }

    /**
     * return all groups declared in LDAP
     * @return string[]
     */

    function ldap_get_grouplist($filter = "*") {
        /// returns all groups from ldap servers

        global $CFG, $DB;

        print_string('connectingldap', 'auth_ldap');
        $ldapconnection = $this->ldap_connect();

        $fresult = array ();

        if ($filter == "*") {
            $filter = "(&(" . $this->config->group_attribute . "=*)(objectclass=" . $this->config->group_class . "))";
        }

        $contexts = explode(';', $this->config->contexts);
        if (!empty ($this->config->create_context)) {
            array_push($contexts, $this->config->create_context);
        }

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                //use ldap_search to find first group from subtree
                $ldap_result = ldap_search($ldapconnection, $context, $filter, array ($this->config->group_attribute));
            } else {
                //search only in this context
                $ldap_result = ldap_list($ldapconnection, $context, $filter, array ($this->config->group_attribute));
            }
            $groups = ldap_get_entries($ldapconnection, $ldap_result);
            if ($CFG->debug_ldap_groupes){
                pp_print_object("groups found in LDAP ctx $context : ", $groups);
            }

            //add found groups to list
            for ($i = 0; $i < count($groups) - 1; $i++) {
                $group_cn=$groups[$i][$this->config->group_attribute][0];
                array_push($fresult, ($groups[$i][$this->config->group_attribute][0]));

                // keep the dn/cn in cache for processing
                if ($this->config->process_nested_groups) {
                    $group_dn=$groups[$i]['dn'];
                    $this->config->groups_dn_cache[$group_dn]=$group_cn;
                }
            }
        }
        $this->ldap_close();
        return $fresult;
    }

    /**
     * serach for group members on a openLDAP directory
     * return string[] array of usernames
     */

    function ldap_get_group_members_rfc($group) {
        global $CFG;

        $ret = array ();
        $ldapconnection = $this->ldap_connect();

        $textlib = textlib_get_instance();
        $group = $textlib->convert($group, 'utf-8', $this->config->ldapencoding);
        //this line break the script with Moodle 2.1 2.2  under windows
        //see http://tracker.moodle.org/browse/MDL-30859
        //$group = textlib::convert($group, 'utf-8', $this->config->ldapencoding);

        if ($CFG->debug_ldap_groupes){
            pp_print_object("connexion ldap RFC: ", $ldapconnection);
        }
        if (!$ldapconnection) {
            return $ret;
        }

        $queryg = "(&({$this->config->group_attribute}=" . trim($group) . ")(objectClass={$this->config->group_class}))";
        if ($CFG->debug_ldap_groupes){
            pp_print_object("queryg: ", $queryg);
        }
        $contexts = explode(';', $this->config->contexts);
        if (!empty ($this->config->create_context)) {
            array_push($contexts, $this->config->create_context);
        }

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            $resultg = ldap_search($ldapconnection, $context, $queryg);

            if (!empty ($resultg) AND ldap_count_entries($ldapconnection, $resultg)) {
                $groupe = ldap_get_entries($ldapconnection, $resultg);
                if ($CFG->debug_ldap_groupes){
                    pp_print_object("groupe: ", $groupe);
                }

                for ($g = 0; $g < (sizeof($groupe[0][$this->config->memberattribute]) - 1); $g++) {

                    $membre = trim($groupe[0][$this->config->memberattribute][$g]);
                    if ($membre != "") { //*3
                        if ($CFG->debug_ldap_groupes){
                            pp_print_object("membre : ", $membre);
                        }
                        // try to speed the search if the member value is
                        // either a simple username (thus must match the Moodle username)
                        // or xx=username with xx = the user attribute name matching Moodle's username
                        // such as uid=jdoe,ou=xxxx,ou=yyyyy
                        $membre_tmp1 = explode(",", $membre);
                        if (count($membre_tmp1) > 1) {
                            if ($CFG->debug_ldap_groupes){
                                pp_print_object("membre_tpl1: ", $membre_tmp1);
                            }
                            $membre_tmp2 = explode("=", trim($membre_tmp1[0]));
                            if ($CFG->debug_ldap_groupes) {
                                pp_print_object("membre_tpl2: ", $membre_tmp2);
                            }
                            //caution in Moodle LDAP attributes names are converted to lowercase
                            // see process_config in auth/ldap/auth.php
                            $found=textlib::strtolower($membre_tmp2[0]) == textlib::strtolower($this->config->user_attribute);
                            //no need to search LDAP in that case
                            if ($found && empty($this->config->no_speedup_ldap)) {//celui de la config
                                //in Moodle usernames are always converted to lowercase
                                // see auto creating or synching users in auth/ldap/auth.php
                                $ret[] =textlib::strtolower( $membre_tmp2[1]);
                            }else {
                                // fetch Moodle username from LDAP or process nested group
                                if ($CFG->debug_ldap_groupes){
                                    pp_print_object("naming attribute is not ", $this->config->user_attribute);
                                }
                                if ($this->config->memberattribute_isdn) {
                                    //rev 1.2 nested groups
                                    if ($this->config->process_nested_groups && ($group_cn=$this->is_ldap_group($membre))) {
                                        if ($CFG->debug_ldap_groupes){
                                            pp_print_object("processing nested group ", $membre);
                                        }
                                        // in case of funny directory where groups are member of groups
                                        if (array_key_exists($membre,$this->anti_recursion_array)) {
                                            if ($CFG->debug_ldap_groupes){
                                                pp_print_object("infinite loop detected skipping", $membre);
                                            }
                                            unset($this->anti_recursion_array[$membre]);
                                            continue;
                                        }
                                        $this->anti_recursion_array[$membre]=1;
                                        $tmp=$this->ldap_get_group_members_rfc ($group_cn);
                                        unset($this->anti_recursion_array[$membre]);
                                        $ret=array_merge($ret,$tmp);
                                    }
                                    else {
                                        if ($cpt = $this->get_username_bydn($membre_tmp2[0], $membre_tmp2[1])) {
                                            $ret[] = $cpt;
                                        }
                                    }
                                }// else nothing to add
                            }
                        } else {
                            $ret[] = textlib::strtolower($membre);
                        }
                    }
                }
            }
        }
        if ($CFG->debug_ldap_groupes){
            pp_print_object("retour get_g_m ", $ret);
        }
        $this->ldap_close();
        return $ret;
    }

    /**
     * specific serach for active Directory  problems if more than 999 members
     * recherche paginée voir http://forums.sun.com/thread.jspa?threadID=578347
     */

    function ldap_get_group_members_ad($group) {
        global $CFG;

        $ret = array ();
        $ldapconnection = $this->ldap_connect();
        if ($CFG->debug_ldap_groupes){
            pp_print_object("connexion ldap AD: ", $ldapconnection);
        }
        if (!$ldapconnection) {
            return $ret;
        }

        $textlib = textlib_get_instance();
        $group = $textlib->convert($group, 'utf-8', $this->config->ldapencoding);
        //this line break the script with Moodle 2.1 2.2  under windows
        //see http://tracker.moodle.org/browse/MDL-30859
        //$group = textlib::convert($group, 'utf-8', $this->config->ldapencoding);

        $queryg = "(&({$this->config->group_attribute}=" . trim($group) . ")(objectClass={$this->config->group_class}))";
        if ($CFG->debug_ldap_groupes){
            pp_print_object("queryg: ", $queryg);
        }

        $size = 999;


        $contexts = explode(';', $this->config->contexts);
        if (!empty ($this->config->create_context)) {
            array_push($contexts, $this->config->create_context);
        }

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }
            $start = 0;
            $end = $size;
            $fini = false;

            while (!$fini) {
                //recherche paginée par paquet de 1000
                $attribut = $this->config->memberattribute . ";range=" . $start . '-' . $end;
                $resultg = ldap_search($ldapconnection, $context, $queryg, array (
                $attribut
                ));

                if (!empty ($resultg) AND ldap_count_entries($ldapconnection, $resultg)) {
                    $groupe = ldap_get_entries($ldapconnection, $resultg);
                    if ($CFG->debug_ldap_groupes) {
                        pp_print_object("groupe: ", $groupe);
                    }

                    // a la derniere passe, AD renvoie member;Range=numero-* !!!
                    if (empty ($groupe[0][$attribut])) {
                        $attribut = $this->config->memberattribute . ";range=" . $start . '-*';
                        $fini = true;
                    }

                    for ($g = 0; $g < (sizeof($groupe[0][$attribut]) - 1); $g++) {

                        $membre = trim($groupe[0][$attribut][$g]);
                        if ($membre != "") { //*3
                            if ($CFG->debug_ldap_groupes) {
                                pp_print_object("membre : ", $membre);
                            }
                            // try to speed the search if the member value is
                            // either a simple username (thus must match the Moodle username)
                            // or xx=username with xx = the user attribute name matching Moodle's username
                            // such as uid=jdoe,ou=xxxx,ou=yyyyy
                            $membre_tmp1 = explode(",", $membre);
                            if (count($membre_tmp1) > 1) {
                                if ($CFG->debug_ldap_groupes) {
                                    pp_print_object("membre_tpl1: ", $membre_tmp1);
                                }
                                $membre_tmp2 = explode("=", trim($membre_tmp1[0]));
                                if ($CFG->debug_ldap_groupes) {
                                    pp_print_object("membre_tpl2: ", $membre_tmp2);
                                }
                                //caution in Moodle LDAP attributes names are converted to lowercase
                                // see process_config in auth/ldap/auth.php
                                $found=textlib::strtolower($membre_tmp2[0]) == textlib::strtolower($this->config->user_attribute);
                                //no need to search LDAP in that case
                                if ($found && empty($this->config->no_speedup_ldap)) {//celui de la config
                                    //in Moodle usernames are always converted to lowercase
                                    // see auto creating or synching users in auth/ldap/auth.php
                                    $ret[] =textlib::strtolower( $membre_tmp2[1]);
                                     
                                }else {
                                    // fetch Moodle username from LDAP or process nested group
                                    if ($CFG->debug_ldap_groupes){
                                        pp_print_object("naming attribute is not ", $this->config->user_attribute);
                                    }
                                    if ($this->config->memberattribute_isdn) {
                                        //rev 1.2 nested groups
                                        if ($this->config->process_nested_groups && ($group_cn=$this->is_ldap_group($membre))) {
                                            if ($CFG->debug_ldap_groupes){
                                                pp_print_object("processing nested group ", $membre);
                                            }
                                            //recursive call
                                            // in case of funny directory where groups are member of groups
                                            if (array_key_exists($membre,$this->anti_recursion_array)) {
                                                if ($CFG->debug_ldap_groupes){
                                                    pp_print_object("infinite loop detected skipping", $membre);
                                                }
                                                unset($this->anti_recursion_array[$membre]);
                                                continue;
                                            }

                                            $this->anti_recursion_array[$membre]=1;
                                            $tmp=$this->ldap_get_group_members_ad ($group_cn);
                                            unset($this->anti_recursion_array[$membre]);
                                            $ret=array_merge($ret,$tmp);
                                        }
                                        else {
                                            if ($cpt = $this->get_username_bydn($membre_tmp2[0], $membre_tmp2[1])) {
                                                $ret[] = $cpt;
                                            }
                                        }
                                    }// else nothing to add
                                }
                            } else {
                                $ret[] = textlib::strtolower($membre);
                            }
                        }
                    }
                } else {
                    $fini = true;
                }
                $start = $start + $size;
                $end = $end + $size;
            }
        }
        if ($CFG->debug_ldap_groupes) {
            pp_print_object("retour get_g_m ", $ret);
        }
        $this->ldap_close();
        return $ret;
    }

    /**
     * was NOT implemented in rev. 1.0
     * should return a 'Moodle account' from its LDAP dn
     * useful in the (not so uncommon case) where user DN is NOT of the form
     *    xx=moodleusername,ou=yyyy,ou=zzzz
     * quite common in AD where user's DN are CN=user fullname, ou=yyyyy ...
     * CAUTION : this function IS internal and expect the ldap connection to be OPEN
     * @param string $dnid  the name of the naming attribute (cn, samaccountname ...)
     * @param string $dn    the value of the naming attribute to search (e.g : John Doe)
     * @return string or false
     */
    private function get_username_bydn($dnid,$dn) {
        global $CFG;
        //build a filter
        // note than nested groups will be removed here, so they are NOT supported
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $filter='(&'.$filter.'('.$dnid.'='.ldap_addslashes($dn).'))';
        if ($CFG->debug_ldap_groupes) {
            pp_print_object('looking for ',$filter);
        }
        // call Moodle ldap_get_userlist that return it as an array with Moodle user attributes names
        $matchings=$this->ldap_get_userlist($filter);
        // return the FIRST entry found
        if (empty($matchings)) {
            if ($CFG->debug_ldap_groupes) {
                pp_print_object('not found','');
            }
            return false;
        }
        if (count($matchings)>1) {
            if ($CFG->debug_ldap_groupes) {
                pp_print_object('error more than one found for ',$count($matchings));
            }
            return false;
        }
        if ($CFG->debug_ldap_groupes) {
            pp_print_object('found ',$matchings);
        }
        //in Moodle usernames are always converted to lowercase
        // see auto creating or synching users in auth/ldap/auth.php
        return textlib::strtolower($matchings[0]);
    }

    /**
     * search the group cn in group names cache
     * this is definitively faster than searching AGAIN LDAP for this dn with class=group...
     * @param string $dn  the group DN
     * @return string the group CN or false
     */
    private function is_ldap_group($dn) {
        if (empty($this->config->process_nested_groups)) {
            return false; // not supported by config
        }
        return !empty($this->config->groups_dn_cache[$dn])? $this->config->groups_dn_cache[$dn]:false ;
    }

    /**
     * rev 1012 traitement de l'execption avec active directory pour des groupes >1000 membres
     * voir http://forums.sun.com/thread.jspa?threadID=578347
     *
     * @return string[] an array of username indexed by Moodle's userid
     */
    function ldap_get_group_members($groupe) {
        global $DB;
        if ($this->config->user_type == "ad") {
            $members = $this->ldap_get_group_members_ad($groupe);
        }
        else {
            $members = $this->ldap_get_group_members_rfc($groupe);
        }
        $ret = array ();
        //remove all LDAP users unkown to Moodle
        foreach ($members as $member) {
            $params = array (
                'username' => $member
            );
            if ($user = $DB->get_record('user', $params, 'id,username')) {
                $ret[$user->id] = $user->username;
            }
        }
        return $ret;
    }

    function get_cohort_members($cohortid) {
        global $DB;
        $sql = " SELECT u.id,u.username
                          FROM {user} u
                         JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                        WHERE u.deleted=0";
        $params['cohortid'] = $cohortid;
        return $DB->get_records_sql($sql, $params);
    }

    function cohort_is_member($cohortid, $userid) {
        global $DB;
        $params = array (
            'cohortid' => $cohortid,
            'userid' => $userid
        );
        return $DB->record_exists('cohort_members', $params);
    }

}

/**
 *
 * verbose debugging function
 * @param unknown_type $title
 * @param unknown_type $obj
 */
function pp_print_object($title, $obj) {
    print $title;
    if (is_object($obj) || is_array($obj)) {
        print_r($obj);
    } else  {
        print ($obj .PHP_EOL);
    }
}

// Ensure errors are well explained
$CFG->debug = DEBUG_NORMAL;
// Disable verbose mode of auth_plugin_cohort()
if (empty($CFG->debug_ldap_groupes)) {
    $CFG->debug_ldap_groupes=false; // remove PHP notices
}



if ( !is_enabled_auth('cas') && !is_enabled_auth('ldap')) {
    error_log('[AUTH CAS] ' . get_string('pluginnotenabled', 'auth_ldap'));
    die;
}

$plugin = new auth_plugin_cohort();

//force search the hard way in my place where member attribute value IS xx=moodleusername,ou=xxxx
//instead of more commun cn=user fullname, ou=xxxx,ou=yyyy
//$plugin->config->no_speedup_ldap=1;
//$plugin->config->user_type = "ad";
//$plugin->config->memberattribute_isdn=1;

// in some places the naming attribute used is not in full lowercase
// this does not bother authentication where LDAP search is done
// but DOES bother this sync plugin when searching user's Moodle name from its DN
//$plugin->config->user_attribute='sAMAccountName';
//testing code force processing of nested groups if not set in config.php ($CFG->ldap_process_nested_groups)
//$plugin->config->process_nested_groups=1;

if ($CFG->debug_ldap_groupes){
    pp_print_object('plugin config',$plugin->config);
}

$ldap_groups = $plugin->ldap_get_grouplist();
print_r($ldap_groups);
if ($CFG->debug_ldap_groupes){
    pp_print_object('plugin group names cache ',$plugin->config->groups_dn_cache);
}



foreach ($ldap_groups as $group=>$groupname) {
    print "traitement du groupe " . $groupname .PHP_EOL;
    $params = array (
        'idnumber' => $groupname
    );
   // not that we search for cohort IDNUMBER and not name for a match 
    // thus it we do not autocreate cohorts, admin MUST create cohorts beforehand
    // and set their IDNUMBER to the exact value of the corresponding attribute in LDAP  
    if (!$cohort = $DB->get_record('cohort', $params, '*')) {
        
        if (empty($CFG->cohort_synching_ldap_groups_autocreate_cohorts)) {
            print ("ignore la cohorte $groupname qui n'existe pas dans Moodle".PHP_EOL);
            continue;
        }
    
        $cohort = new StdClass();
        $cohort->name = $cohort->idnumber = $groupname;
        $cohort->contextid = get_system_context()->id;
        //$cohort->component='sync_ldap';
        $cohort->description='cohorte synchronisée avec notre LDAP';
        $cohortid = cohort_add_cohort($cohort);
        print "creation cohorte " . $group .PHP_EOL;

    } else {
        $cohortid = $cohort->id;
    }
    //    print ($cohortid." ");
    $ldap_members = $plugin->ldap_get_group_members($groupname);
    if ($CFG->debug_ldap_groupes){
        pp_print_object("members of LDAP group $groupname known to Moodle", $ldap_members);
    }

    $cohort_members = $plugin->get_cohort_members($cohortid);
    if ($CFG->debug_ldap_groupes){
        pp_print_object("current members of cohort $groupname", $cohort_members);
    }
    foreach ($cohort_members as $userid => $user) {
        if (!isset ($ldap_members[$userid])) {
            cohort_remove_member($cohortid, $userid);
            print "desinscription de " .
            $user->username .
            " de la cohorte " .
            $groupname .
            PHP_EOL;
        }
    }

    foreach ($ldap_members as $userid => $username) {
        if (!$plugin->cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
            print "inscription de " . $username . " à la cohorte " . $groupname .PHP_EOL;
        }
    }
    //break;

}
