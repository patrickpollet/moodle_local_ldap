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
 * Code for handling synching Moodle's cohorts with LDAP
 *
 * @package local_ldap
 * @copyright 2013 onwards Patrick Pollet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/auth/ldap/auth.php');

/**
 * LDAP cohort sychronization.
 */
class local_ldap extends auth_plugin_ldap {

    // Avoid infinite loop with nested groups in 'funny' directories.
    private $antirecursionarray;

    // Cache for found group dns.
    private $groupdnscache;

    /**
     * Constructor.
     */

    public function __construct() {
        // Revision March 2013 needed to fetch the proper LDAP parameters
        // host, context ... from table config_plugins see comments in https://tracker.moodle.org/browse/MDL-25011.
        if (is_enabled_auth('cas')) {
            $this->authtype = 'cas';
            $this->roleauth = 'auth_cas';
            $this->errorlogtag = '[AUTH CAS] ';
        } else if (is_enabled_auth('ldap')) {
            $this->authtype = 'ldap';
            $this->roleauth = 'auth_ldap';
            $this->errorlogtag = '[AUTH LDAP] ';
        } else {
            return false;
        }

        // Fetch basic settings from LDAP or CAS auth plugin.
        $this->init_plugin($this->authtype);

        // Get my specific settings.
        $extra = get_config('local_ldap');
        $this->merge_config($extra, 'group_attribute', 'cn');
        $this->merge_config($extra, 'group_class', 'groupOfNames');
        $this->merge_config($extra, 'process_nested_groups', 0);
        $this->merge_config($extra, 'cohort_synching_ldap_attribute_attribute', 'eduPersonAffiliation');
        $this->merge_config($extra, 'cohort_synching_ldap_attribute_idnumbers', '');
        $this->merge_config($extra, 'cohort_synching_ldap_groups_autocreate_cohorts', false);
        $this->merge_config($extra, 'cohort_synching_ldap_attribute_autocreate_cohorts', false);

         // Moodle DO convert to lowercase all LDAP attributes in setting screens
         // this cause an issue when searching LDAP group members when user's naming attribute
         // is in mixed case in the LDAP , such as sAMAccountName instead of samaccountname
         // If your cohorts are not populated by this script try setting this value.
        if (!empty($extra->real_user_attribute)) {
            $this->config->user_attribute = $extra->real_user_attribute;
        }

        // Override if needed the object class defined in Moodle's LDAP settings
        // useful to restrict this synching to a certain category of LDAP users such as students.
        if (! empty($extra->cohort_synching_ldap_attribute_objectclass)) {
            $this->config->objectclass = $extra->cohort_synching_ldap_attribute_objectclass;
        }

        // Cache for found groups dn; used for nested groups processing.
        $this->groupdnscache      = array();
        $this->antirecursionarray = array();
    }

    /**
     *
     * merge configuration setting
     * @param unknown_type $from
     * @param unknown_type $key
     * @param unknown_type $default
     */
    private function merge_config ($from, $key, $default) {
        if (!empty($from->$key)) {
            $this->config->$key = $from->$key;
        } else {
            $this->config->$key = $default;
        }
    }

    /**
     * Return all groups declared in LDAP.
     * @return string[]
     */
    public function ldap_get_grouplist($filter = "*") {
        global $CFG;

        $ldapconnection = $this->ldap_connect();

        $fresult = array ();

        if ($filter == "*") {
            $filter = "(&(" . $this->config->group_attribute . "=*)(objectclass=" . $this->config->group_class . "))";
        }

        if (!empty($CFG->cohort_synching_ldap_groups_contexts)) {
            $contexts = explode(';', $CFG->cohort_synching_ldap_groups_contexts);
        } else {
            $contexts = explode(';', $this->config->contexts);
        }

        if (!empty ($this->config->create_context)) {
            array_push($contexts, $this->config->create_context);
        }

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                // Use ldap_search to find first group from subtree.
                $ldapresult = ldap_search($ldapconnection, $context, $filter, array ($this->config->group_attribute));
            } else {
                // Search only in this context.
                $ldapresult = ldap_list($ldapconnection, $context, $filter, array ($this->config->group_attribute));
            }
            $groups = ldap_get_entries($ldapconnection, $ldapresult);

            // Add found groups to list.
            for ($i = 0; $i < count($groups) - 1; $i++) {
                $groupcn = $groups[$i][$this->config->group_attribute][0];
                array_push($fresult, ($groups[$i][$this->config->group_attribute][0]));

                // Keep the dn/cn in cache for processing.
                if ($this->config->process_nested_groups) {
                    $groupdn = $groups[$i]['dn'];
                    $this->groupdnscache[$groupdn] = $groupcn;
                }
            }
        }
        $this->ldap_close();
        return $fresult;
    }

    /**
     * Search for group members on a openLDAP directory.
     * return string[] array of usernames
     */

    private function ldap_get_group_members_rfc($group) {
        global $CFG;

        $ret = array ();
        $ldapconnection = $this->ldap_connect();

        $group = core_text::convert($group, 'utf-8', $this->config->ldapencoding);

        if (!$ldapconnection) {
            return $ret;
        }

        $queryg = "(&({$this->config->group_attribute}=" . ldap_filter_addslashes(trim($group)) . ")(objectClass={$this->config->group_class}))";

        if (!empty($CFG->cohort_synching_ldap_groups_contexts)) {
            $contexts = explode(';', $CFG->cohort_synching_ldap_groups_contexts);
        } else {
            $contexts = explode(';', $this->config->contexts);
        }

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

                for ($g = 0; $g < (count($groupe[0][$this->config->memberattribute]) - 1); $g++) {

                    $memberstring = trim($groupe[0][$this->config->memberattribute][$g]);
                    if ($memberstring != "") {
                        // Try to speed the search if the member value is
                        // either a simple username (thus must match the Moodle username)
                        // or xx=username with xx = the user attribute name matching Moodle's username
                        // such as uid=jdoe,ou=xxxx,ou=yyyyy.
                        $member = explode(",", $memberstring);
                        if (count($member) > 1) {
                            $memberparts = explode("=", trim($member[0]));

                            // Caution in Moodle LDAP attributes names are converted to lowercase
                            // see process_config in auth/ldap/auth.php.
                            $found = core_text::strtolower($memberparts[0]) == core_text::strtolower($this->config->user_attribute);

                            // No need to search LDAP in that case.
                            if ($found && empty($this->config->no_speedup_ldap)) {
                                // In Moodle usernames are always converted to lowercase
                                // see auto creating or synching users in auth/ldap/auth.php.
                                $ret[] = core_text::strtolower($memberparts[1]);
                            } else {
                                // Fetch Moodle username from LDAP or process nested group.
                                if ($this->config->memberattribute_isdn) {
                                    // Rev 1.2 nested groups.
                                    if ($this->config->process_nested_groups && ($groupcn = $this->is_ldap_group($memberstring))) {
                                        // In case of funny directory where groups are member of groups.
                                        if (array_key_exists($memberstring, $this->antirecursionarray)) {
                                            unset($this->antirecursionarray[$memberstring]);
                                            continue;
                                        }
                                        $this->antirecursionarray[$memberstring] = 1;
                                        $tmp = $this->ldap_get_group_members_rfc($groupcn);
                                        unset($this->antirecursionarray[$memberstring]);
                                        $ret = array_merge($ret, $tmp);
                                    } else {
                                        if ($cpt = $this->get_username_byattr($memberparts[0], $memberparts[1])) {
                                            $ret[] = $cpt;
                                        }
                                    }
                                } // Else nothing to add.
                            }
                        } else {
                            $ret[] = core_text::strtolower($memberstring);
                        }
                    }
                }
            }
        }
        $this->ldap_close();
        return $ret;
    }

    /**
     * specific serach for active Directory  problems if more than 999 members
     * recherche paginée voir http://forums.sun.com/thread.jspa?threadID=578347
     */

    private function ldap_get_group_members_ad($group) {
        global $CFG;

        $ret = array ();
        $ldapconnection = $this->ldap_connect();
        if (!$ldapconnection) {
            return $ret;
        }

        $group = core_text::convert($group, 'utf-8', $this->config->ldapencoding);

        $queryg = "(&({$this->config->group_attribute}=" . ldap_filter_addslashes(trim($group)) . ")(objectClass={$this->config->group_class}))";

        $size = 999;

        if (!empty($CFG->cohort_synching_ldap_groups_contexts)) {
            $contexts = explode(';', $CFG->cohort_synching_ldap_groups_contexts);
        } else {
            $contexts = explode(';', $this->config->contexts);
        }

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
                // Recherche paginée par paquet de 1000. TODO: Translate.
                $attribut = $this->config->memberattribute . ";range=" . $start . '-' . $end;
                $resultg = ldap_search($ldapconnection, $context, $queryg, array (
                $attribut
                ));

                if (!empty ($resultg) AND ldap_count_entries($ldapconnection, $resultg)) {
                    $groupe = ldap_get_entries($ldapconnection, $resultg);

                    // A la derniere passe, AD renvoie member;Range=numero-* !!! TODO: Translate.
                    if (empty ($groupe[0][$attribut])) {
                        $attribut = $this->config->memberattribute . ";range=" . $start . '-*';
                        $fini = true;
                    }

                    for ($g = 0; $g < (count($groupe[0][$attribut]) - 1); $g++) {

                        $memberstring = trim($groupe[0][$attribut][$g]);
                        if ($memberstring != "") {
                            // In AD, group object's member values are always full DNs.
                            if ($this->config->process_nested_groups && ($groupcn = $this->is_ldap_group($memberstring))) {
                                // Recursive call in case of funny directory where groups are member of groups.
                                if (array_key_exists($memberstring, $this->antirecursionarray)) {
                                    unset($this->antirecursionarray[$memberstring]);
                                    continue;
                                }

                                $this->antirecursionarray[$memberstring] = 1;
                                $tmp = $this->ldap_get_group_members_ad($groupcn);
                                unset($this->antirecursionarray[$memberstring]);
                                $ret = array_merge($ret, $tmp);
                            } else {
                                if ($cpt = $this->get_username_bydn($memberstring)) {
                                    $ret[] = $cpt;
                                }
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
        $this->ldap_close();
        return $ret;
    }

    /**
     * Returns a Moodle username from an LDAP attribute search
     * @param string $attr  the name of the naming attribute (cn, samaccountname ...)
     * @param string $value the value of the naming attribute to search (e.g : John Doe)
     * @return string or false
     */
    private function get_username_byattr($attr, $value) {
        // Build a filter; note than nested groups will be removed here, so they are NOT supported.
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $filter = '(&'.$filter.'('.$attr.'='.$value.'))';

        // Call Moodle ldap_get_userlist that return it as an array with Moodle user attributes names.
        $matchings = $this->ldap_get_userlist($filter);

        // Return the FIRST entry found.
        if (empty($matchings)) {
            return false;
        }
        if (count($matchings) > 1) {
            return false;
        }

        // In Moodle usernames are always converted to lowercase
        // see auto creating or synching users in auth/ldap/auth.php.
        return core_text::strtolower($matchings[0]);
    }

    /**
     * Returns a Moodle username from an LDAP DN
     * @param string $dn    LDAP user DN
     * @return string or false
     */
    private function get_username_bydn($dn) {
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $ldapconnection = $this->ldap_connect();
        $ldapresult = ldap_read($ldapconnection, $dn, $filter, array($this->config->user_attribute));

        if (!$ldapresult) {
            return false;
        }

        $user = ldap_get_entries_moodle($ldapconnection, $ldapresult);

        if (empty($user)) {
            return false;
        }

        $matching = core_text::convert($user[0][$this->config->user_attribute][0], $this->config->ldapencoding, 'utf-8');

        // In Moodle usernames are always converted to lowercase
        // see auto creating or synching users in auth/ldap/auth.php.
        return core_text::strtolower($matching);
    }


    /**
     * search the group cn in group names cache
     * this is definitively faster than searching AGAIN LDAP for this dn with class=group...
     * @param string $dn  the group DN
     * @return string the group CN or false
     */
    private function is_ldap_group($dn) {
        if (empty($this->config->process_nested_groups)) {
            return false; // Not supported by config.
        }
        return !empty($this->groupdnscache[$dn]) ? $this->groupdnscache[$dn] : false;
    }

    /**
     * rev 1012 traitement de l'execption avec active directory pour des groupes >1000 members
     * voir http://forums.sun.com/thread.jspa?threadID=578347
     *
     * @return string[] an array of username indexed by Moodle's userid
     */
    public function ldap_get_group_members($groupe) {
        global $DB;

        if ($this->config->user_type == "ad") {
            $members = $this->ldap_get_group_members_ad($groupe);
        } else {
            $members = $this->ldap_get_group_members_rfc($groupe);
        }
        $ret = array();
        // Remove all LDAP users unknown to Moodle.
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


    /**
     *
     * Returns the distinct values of the target LDAP attribute
     * these will be the idnumbers of the synched Moodle cohorts
     * @returns array of string
     */
    public function get_attribute_distinct_values() {
        // Only these cohorts will be synched.
        if (!empty($this->config->cohort_synching_ldap_attribute_idnumbers )) {
            return explode(',', $this->config->cohort_synching_ldap_attribute_idnumbers);
        }

        // Build a filter to fetch all users having something in the target LDAP attribute.
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $filter = '(&'.$filter.'('.$this->config->cohort_synching_ldap_attribute_attribute.'=*))';

        $ldapconnection = $this->ldap_connect();

        $contexts = explode(';', $this->config->contexts);
        if (!empty($this->config->create_context)) {
              array_push($contexts, $this->config->create_context);
        }
        $matchings = array();

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                // Use ldap_search to find first user from subtree.
                $ldapresult = ldap_search($ldapconnection, $context,
                                           $filter,
                                           array($this->config->cohort_synching_ldap_attribute_attribute));
            } else {
                // Search only in this context.
                $ldapresult = ldap_list($ldapconnection, $context,
                                         $filter,
                                         array($this->config->cohort_synching_ldap_attribute_attribute));
            }

            if (!$ldapresult) {
                continue;
            }

            // This API function returns all attributes as an array
            // whether they are single or multiple.
            $users = ldap_get_entries_moodle($ldapconnection, $ldapresult);
            $attributekey = strtolower($this->config->cohort_synching_ldap_attribute_attribute); // MDL-57558.

            // Add found DISTINCT values to list.
            for ($i = 0; $i < count($users); $i++) {
                $count = $users[$i][$attributekey]['count'];
                for ($j = 0; $j < $count; $j++) {
                    $value = core_text::convert($users[$i][$attributekey][$j],
                                $this->config->ldapencoding, 'utf-8');
                    if (!in_array ($value, $matchings)) {
                        array_push($matchings, $value);
                    }
                }
            }
        }

        $this->ldap_close();
        return $matchings;
    }

    public function get_users_having_attribute_value($attributevalue) {
        global $DB;

        // Build a filter.
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        $filter = '(&'.$filter.'('.$this->config->cohort_synching_ldap_attribute_attribute.
            '='.ldap_filter_addslashes($attributevalue).'))';

        // Call Moodle ldap_get_userlist that return it as an array with Moodle user attributes names.
        $matchings = $this->ldap_get_userlist($filter);

        // Return the FIRST entry found.
        if (empty($matchings)) {
            return array();
        }

        $ret = array ();
        // Remove all matching LDAP users unkown to Moodle.
        foreach ($matchings as $member) {
            $params = array (
                'username' => $member
            );
            if ($user = $DB->get_record('user', $params, 'id,username')) {
                $ret[$user->id] = $user->username;
            }
        }

        return $ret;
    }

    // TODO: document.
    public function get_cohort_members($cohortid) {
        global $DB;
        $sql = " SELECT u.id,u.username
                          FROM {user} u
                         JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                        WHERE u.deleted=0";
        $params['cohortid'] = $cohortid;
        return $DB->get_records_sql($sql, $params);
    }

    // TODO: document.
    public function cohort_is_member($cohortid, $userid) {
        global $DB;
        $params = array (
            'cohortid' => $cohortid,
            'userid' => $userid
        );
        return $DB->record_exists('cohort_members', $params);
    }

    public function sync_cohorts_by_attribute() {
        global $DB;

        $cohortnames = $this->get_attribute_distinct_values();
        foreach ($cohortnames as $cohortname) {
            // Not that we search for cohort IDNUMBER and not name for a match
            // thus it we do not autocreate cohorts, admin MUST create cohorts beforehand
            // and set their IDNUMBER to the exact value of the corresponding attribute in LDAP.
            if (!$cohort = $DB->get_record('cohort', array('idnumber' => $cohortname), '*')) {
                if (empty($this->config->cohort_synching_ldap_attribute_autocreate_cohorts)) {
                    // The cohort does not exist and auto-creation of cohorts is disabled.
                    continue;
                }

                $ldapmembers = $this->get_users_having_attribute_value($cohortname);
                if (count($ldapmembers) == 0) {
                    // Do not create an empty cohort.
                    continue;
                }

                $cohort = new stdClass();
                $cohort->name = $cohort->idnumber = $cohortname;
                $cohort->contextid = context_system::instance()->id;
                $cohort->description = get_string('cohort_synchronized_with_attribute', 'local_ldap',
                    $this->config->cohort_synching_ldap_attribute_attribute);
                $cohortid = cohort_add_cohort($cohort);
            } else {
                $cohortid = $cohort->id;
                $ldapmembers = $this->get_users_having_attribute_value($cohortname);
            }

            $cohortmembers = $this->get_cohort_members($cohortid);
            foreach ($cohortmembers as $userid => $user) {
                if (!isset($ldapmembers[$userid])) {
                    cohort_remove_member($cohortid, $userid);
                }
            }

            foreach ($ldapmembers as $userid => $username) {
                if (!cohort_is_member($cohortid, $userid)) {
                    cohort_add_member($cohortid, $userid);
                }
            }
        }
        return true;
    }

    public function sync_cohorts_by_group() {
        global $DB;

        $ldapgroups = $this->ldap_get_grouplist();
        foreach ($ldapgroups as $groupname) {
            if (!$cohort = $DB->get_record('cohort', array('idnumber' => $groupname), '*')) {
                if (empty($this->config->cohort_synching_ldap_groups_autocreate_cohorts)) {
                    // The cohort does not exist and auto-creation of cohorts is disabled.
                    continue;
                }
                $ldapmembers = $this->ldap_get_group_members($groupname);
                if (count($ldapmembers) == 0) {
                    // Do not create an empty cohort.
                    continue;
                }
                $cohort = new stdClass();
                $cohort->name = $cohort->idnumber = $groupname;
                $cohort->contextid = context_system::instance()->id;
                $cohort->description = get_string('cohort_synchronized_with_group', 'local_ldap', $groupname);
                $cohortid = cohort_add_cohort($cohort);
            } else {
                $cohortid = $cohort->id;
                $ldapmembers = $this->ldap_get_group_members($groupname);
            }

            // Update existing membership.
            $cohortmembers = $this->get_cohort_members($cohortid);

            // Remove local Moodle users not present in LDAP.
            foreach ($cohortmembers as $userid => $user) {
                if (!isset($ldapmembers[$userid])) {
                    cohort_remove_member($cohortid, $userid);
                }
            }

            // Add LDAP users not present in the local cohort.
            foreach ($ldapmembers as $userid => $username) {
                if (!cohort_is_member($cohortid, $userid)) {
                    cohort_add_member($cohortid, $userid);
                }
            }
        }
        return true;
    }
}
