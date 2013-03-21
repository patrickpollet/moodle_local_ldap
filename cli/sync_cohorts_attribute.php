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
 * cohort sync with LDAP attribute script.
 *
 * This script is meant to be called from a cronjob to sync moodle's cohorts
 * with users having some values in an LDAP attribute
 * where the CAS/LDAP backend acts as 'master'.
 * 
 * It is based on code of sync_cohorts that synchronize Moodle's cohorts with LDAP groups
 * see https://tracker.moodle.org/browse/MDL-25011
 *
 * Sample cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/auth/cas/cli/sync_cohorts_attributes.php
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
 * @subpackage LDAP 
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
 * $CFG->cohort_synching_ldap_attribute_attribute='eduPersonAffiliation';     // adjust to the attribute to search for
 * $CFG->cohort_synching_ldap_attribute_idnumbers='comma separated list of target cohorts idnumbers'; // if missing ALL distinct values of the attribute will produce a synched cohort
 * $CFG->cohort_synching_ldap_attribute_verbose=false;           // turn on extensive debug upon running
 * $CFG->cohort_synching_ldap_attribute_objectclass // if set override default value inherited from LDAP auth plugin 
 * $CFG->cohort_synching_ldap_attribute_autocreate_cohorts // if false will not create missing cohorts (admin must create them before) 
 * 
 */

/**
 * REVISIONS
 * 1.0 08 March 2013 initial release based on code synchying cohorts with LDAP groups
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
        $this->config->cohort_synching_ldap_attribute_attribute = !empty($CFG->cohort_synching_ldap_attribute_attribute)?$CFG->cohort_synching_ldap_attribute_attribute:'eduPersonAffiliation';
        if (!empty($CFG->cohort_synching_ldap_attribute_idnumbers)) {
            $this->config->cohort_synching_ldap_attribute_idnumbers = explode(',',$CFG->cohort_synching_ldap_attribute_idnumbers);
        } else {
            $this->config->cohort_synching_ldap_attribute_idnumbers =array(); 
        }
        //override if needed the object class defined in Moodle's LDAP settings
        //useful to restrict this synching to a certain category of LDAP users such as students 
        if (! empty($CFG->cohort_synching_ldap_attribute_objectclass)) {
            $this->config->objectclass=$CFG->cohort_synching_ldap_attribute_objectclass;
        }
         

        if ($CFG->cohort_synching_ldap_attribute_verbose){
            pp_print_object('plugin config',$this->config);
        }

    }
      
    /**
     * 
     * returns the distinct values of the target LDAP attribute
     * these will be the idnumbers of the synched Moodle cohorts
     * @returns array of string 
     */
    function get_attribute_distinct_values() {
       
        //return array ('affiliate','retired','student','faculty','staff','employee','affiliate','member','alum','emeritus','researcher');
       
        global $CFG, $DB;
        // only these cohorts will be synched 
        if (!empty($this->config->cohort_synching_ldap_attribute_idnumbers )) {
            return $this->config->cohort_synching_ldap_attribute_idnumbers ;
        }
        
        
        //build a filter to fetch all users having something in the target LDAP attribute 
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $filter='(&'.$filter.'('.$this->config->cohort_synching_ldap_attribute_attribute.'=*))';
        if ($CFG->cohort_synching_ldap_attribute_verbose) {
            pp_print_object('looking for ',$filter);
        }

        $ldapconnection = $this->ldap_connect();

        $contexts = explode(';', $this->config->contexts);
        if (!empty($this->config->create_context)) {
              array_push($contexts, $this->config->create_context);
        }
        $matchings=array();

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                // Use ldap_search to find first user from subtree
                $ldap_result = ldap_search($ldapconnection, $context,
                                           $filter,
                                           array($this->config->cohort_synching_ldap_attribute_attribute));
            } else {
                // Search only in this context
                $ldap_result = ldap_list($ldapconnection, $context,
                                         $filter,
                                         array($this->config->cohort_synching_ldap_attribute_attribute));
            }

            if(!$ldap_result) {
                continue;
            }

            // this API function returns all attributes as an array 
            // wether they are single or multiple 
            $users = ldap_get_entries_moodle($ldapconnection, $ldap_result);
            
            // Add found DISTINCT values to list
           for ($i = 0; $i < count($users); $i++) {
               $count=$users[$i][$this->config->cohort_synching_ldap_attribute_attribute]['count'];
               for ($j=0; $j <$count; $j++) {
                   $value=  textlib::convert($users[$i][$this->config->cohort_synching_ldap_attribute_attribute][$j],
                                $this->config->ldapencoding, 'utf-8');
                   if (! in_array ($value, $matchings)) {
                       array_push($matchings,$value);
                   }
               }
            }
        }

        $this->ldap_close();   
        return $matchings;
        
        
        
    }
    
     
    function get_users_having_attribute_value ($attributevalue) {
            global $CFG, $DB;
        //build a filter
 
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $filter='(&'.$filter.'('.$this->config->cohort_synching_ldap_attribute_attribute.'='.ldap_addslashes($attributevalue).'))';
        if ($CFG->cohort_synching_ldap_attribute_verbose) {
            pp_print_object('looking for ',$filter);
        }
        // call Moodle ldap_get_userlist that return it as an array with Moodle user attributes names
        $matchings=$this->ldap_get_userlist($filter);
        // return the FIRST entry found
        if (empty($matchings)) {
            if ($CFG->cohort_synching_ldap_attribute_verbose) {
                pp_print_object('not found','');
            }
            return array();
        }
     if ($CFG->cohort_synching_ldap_attribute_verbose) {
             pp_print_object('found ',count($matchings). ' matching users in LDAP');
        }
        
          $ret = array ();
        //remove all matching LDAP users unkown to Moodle
        foreach ($matchings as $member) {
            $params = array (
                'username' => $member
            );
            if ($user = $DB->get_record('user', $params, 'id,username')) {
                $ret[$user->id] = $user->username;
            }
        }
        if ($CFG->cohort_synching_ldap_attribute_verbose) {
                pp_print_object('found ',count($ret). ' matching users known to Moodle');
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
if (empty($CFG->cohort_synching_ldap_attribute_verbose)) {
    $CFG->cohort_synching_ldap_attribute_verbose=false; // remove PHP notices
}

// testing code 
//$CFG->cohort_synching_ldap_attribute_verbose=1;
//$CFG->cohort_synching_ldap_attribute_idnumbers="faculty,staff,student,teacher"; 

if ( !is_enabled_auth('cas') && !is_enabled_auth('ldap')) {
    error_log('[AUTH CAS] ' . get_string('pluginnotenabled', 'auth_ldap'));
    die;
}

$plugin = new auth_plugin_cohort();

$cohort_names = $plugin->get_attribute_distinct_values();

if ($CFG->cohort_synching_ldap_attribute_verbose){ 
    pp_print_object("cohort idnumbers", $cohort_names);
}    


foreach ($cohort_names as $n=>$cohortname) {
    print "traitement des " . $cohortname .PHP_EOL;
    $params = array (
        'idnumber' => $cohortname
    );
    // not that we search for cohort IDNUMBER and not name for a match 
    // thus it we do not autocreate cohorts, admin MUST create cohorts beforehand
    // and set their IDNUMBER to the exact value of the corresponding attribute in LDAP  
    if (!$cohort = $DB->get_record('cohort', $params, '*')) {
        
        if (empty($CFG->cohort_synching_ldap_attribute_autocreate_cohorts)) {
            print ("ignore la cohorte $cohortname qui n'existe pas dans Moodle".PHP_EOL);
            continue;
        }
          
        
        $cohort = new StdClass();
        $cohort->name = $cohort->idnumber = $cohortname;
        $cohort->contextid = get_system_context()->id;
        $cohort->description='cohorte synchronisée avec attribut LDAP '.$plugin->config->cohort_synching_ldap_attribute_attribute;
        $cohortid = cohort_add_cohort($cohort);
        print "creation cohorte " . $cohortname .PHP_EOL;

    } else {
        $cohortid = $cohort->id;
    }
    //    print ($cohortid." ");
    $ldap_members = $plugin->get_users_having_attribute_value ($cohortname);
    if ($CFG->cohort_synching_ldap_attribute_verbose){
        pp_print_object("members of LDAP  $cohortname known to Moodle", $ldap_members);
    }

    $cohort_members = $plugin->get_cohort_members($cohortid);
   // if ($CFG->cohort_synching_ldap_attribute_verbose){
   //     pp_print_object("current members of cohort $cohortname", $cohort_members);
   // }
    foreach ($cohort_members as $userid => $user) {
        if (!isset ($ldap_members[$userid])) {
           cohort_remove_member($cohortid, $userid);
            print "desinscription de " .
            $user->username .
            " de la cohorte " .
            $cohortname .
            PHP_EOL;
        }
    }

    foreach ($ldap_members as $userid => $username) {
        if (!$plugin->cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
            print "inscription de " . $username . " à la cohorte " . $cohortname .PHP_EOL;
        }
    }
   //break;

}
