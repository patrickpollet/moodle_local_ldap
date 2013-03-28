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
 * and the following default values that can be altered in settings page
 * group_attribute='cn';          // in case your groups are not cn=xxx,
 * group_class='groupofuniquenames'; // in case your groups are of class group ...
 * real_user_attribute='uid';     // in case your user_attribute is in mixed case in LDAP (sAMAccountName) 
 * process_nested_groups=0;       // turn on nested groups
 * debug_ldap_groupes=false;           // turn on extensive debug upon running
 * cohort_synching_ldap_groups_autocreate_cohorts // if false will not create missing cohorts (admin must create them before) 
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

require (dirname(dirname(__FILE__)) . '/locallib.php');


// Ensure errors are well explained
$CFG->debug = DEBUG_NORMAL;

if ( !is_enabled_auth('cas') && !is_enabled_auth('ldap')) {
    error_log('[AUTH CAS] ' . get_string('pluginnotenabled', 'auth_ldap'));
    die;
}
$starttime = microtime();
$plugin = new auth_plugin_cohort();

$ldap_groups = $plugin->ldap_get_grouplist();

if ($CFG->debug_ldap_groupes){
    pp_print_object('plugin group names cache ',$plugin->groups_dn_cache);
}


foreach ($ldap_groups as $group=>$groupname) {
    print "processing LDAP group " . $groupname .PHP_EOL;
    $params = array (
        'idnumber' => $groupname
    );
   // not that we search for cohort IDNUMBER and not name for a match 
    // thus it we do not autocreate cohorts, admin MUST create cohorts beforehand
    // and set their IDNUMBER to the exact value of the corresponding attribute in LDAP  
    if (!$cohort = $DB->get_record('cohort', $params, '*')) {
        
        if (empty($plugin->config->cohort_synching_ldap_groups_autocreate_cohorts)) {
            print ("ignoring $groupname that does not exist in Moodle (autocreation is off)".PHP_EOL);
            continue;
        }
        
        $ldap_members = $plugin->ldap_get_group_members($groupname);
        
        // do not create yet the cohort if no known Moodle users are concerned
        if (count($ldap_members)==0) {
            print "not autocreating empty cohort " . $groupname .PHP_EOL;
            continue;
        }
    
        $cohort = new StdClass();
        $cohort->name = $cohort->idnumber = $groupname;
        $cohort->contextid = context_system::instance()->id;
        //$cohort->component='sync_ldap';
        $cohort->description=get_string('cohort_synchronized_with_group','local_ldap',$groupname);
        //print_r($cohort);
        $cohortid = cohort_add_cohort($cohort);
        print "creating cohort " . $group .PHP_EOL;

    } else {
        $cohortid = $cohort->id;
        $ldap_members = $plugin->ldap_get_group_members($groupname);
    }
 

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
            print "removing " .$user->username ." from cohort " .$groupname . PHP_EOL;
        }
    }

    foreach ($ldap_members as $userid => $username) {
        if (!$plugin->cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
            print "adding " . $username . " to cohort " . $groupname .PHP_EOL;
        }
    }
    //break;

}

$difftime = microtime_diff($starttime, microtime());
print("Execution took ".$difftime." seconds".PHP_EOL);
