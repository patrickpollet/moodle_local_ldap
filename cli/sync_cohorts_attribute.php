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
 * This script should be run some time after /var/www/moodle/auth/cas/cli/sync_users.php
 *
 * Performance notes:
 * We have optimized it as best as we could for PostgreSQL and MySQL, with 27K students
 * we have seen this take 10 minutes.
 *
 * @package    auth
 * @subpackage ldap
 * @copyright  2010 Patrick Pollet - based on code by Jeremy Guittirez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * CONFIGURATION
 * This script make use of current Moodle's LDAP/CAS settings
 * user_attribute
 * member_attribute
 * member_attribute_isdn
 * objectclass
 *
 * and the following default values that can be altered in settings page
 * cohort_synching_ldap_attribute_attribute='eduPersonAffiliation';     // adjust to the attribute to search for
 * cohort_synching_ldap_attribute_idnumbers='comma separated list of target cohorts idnumbers'; // if missing ALL distinct values of the attribute will produce a synched cohort
 * debug_ldap_groupes=false;           // turn on extensive debug upon running
 * cohort_synching_ldap_attribute_objectclass // if set override default value inherited from LDAP auth plugin
 * cohort_synching_ldap_attribute_autocreate_cohorts // if false will not create missing cohorts (admin must create them before)
 *
 */

/**
 * REVISIONS
 * 1.0 08 March 2013 initial release based on code synchying cohorts with LDAP groups
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require(dirname(dirname(__FILE__)) . '/locallib.php');

// Ensure errors are well explained.
$CFG->debug = DEBUG_NORMAL;

if ( !is_enabled_auth('cas') && !is_enabled_auth('ldap')) {
    error_log('[AUTH CAS] ' . get_string('pluginnotenabled', 'auth_ldap'));
    die;
}
$starttime = microtime();
$plugin = new auth_plugin_cohort();

$cohort_names = $plugin->get_attribute_distinct_values();

if ($CFG->debug_ldap_groupes) {
    pp_print_object("cohort idnumbers", $cohort_names);
}

foreach ($cohort_names as $n => $cohortname) {
    print "processing cohort " . $cohortname .PHP_EOL;
    $params = array (
        'idnumber' => $cohortname
    );
    // Not that we search for cohort IDNUMBER and not name for a match
    // thus it we do not autocreate cohorts, admin MUST create cohorts beforehand
    // and set their IDNUMBER to the exact value of the corresponding attribute in LDAP.
    if (!$cohort = $DB->get_record('cohort', $params, '*')) {

        if (empty($plugin->config->cohort_synching_ldap_attribute_autocreate_cohorts)) {
             print ("ignoring $cohortname that does not exist in Moodle (autocreation is off)".PHP_EOL);
            continue;
        }

        $ldap_members = $plugin->get_users_having_attribute_value ($cohortname);

        // Do not create yet the cohort if no known Moodle users are concerned.
        if (count($ldap_members) == 0) {
            print "not creating empty cohort " . $cohortname .PHP_EOL;
            continue;
        }

        $cohort = new StdClass();
        $cohort->name = $cohort->idnumber = $cohortname;
        $cohort->contextid = context_system::instance()->id;
        $cohort->description = get_string('cohort_synchronized_with_attribute', 'local_ldap',
            $plugin->config->cohort_synching_ldap_attribute_attribute);
        $cohortid = cohort_add_cohort($cohort);
        print "creating cohort " . $cohortname .PHP_EOL;

    } else {
        $cohortid = $cohort->id;
        $ldap_members = $plugin->get_users_having_attribute_value($cohortname);
    }

    if ($CFG->debug_ldap_groupes) {
        pp_print_object("members of LDAP  $cohortname known to Moodle", $ldap_members);
    }

    $cohort_members = $plugin->get_cohort_members($cohortid);
    foreach ($cohort_members as $userid => $user) {
        if (!isset ($ldap_members[$userid])) {
            cohort_remove_member($cohortid, $userid);
            print "removing " .$user->username ." from cohort " . $cohortname . PHP_EOL;
        }
    }

    foreach ($ldap_members as $userid => $username) {
        if (!$plugin->cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
            print "adding " . $username . " to cohort " . $cohortname .PHP_EOL;
        }
    }
}
$difftime = microtime_diff($starttime, microtime());
print("Execution took ".$difftime." seconds".PHP_EOL);
