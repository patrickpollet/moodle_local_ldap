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
 * @package    local_ldap
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

require(__DIR__.'/../../../config.php'); // Global moodle config file.
require_once($CFG->dirroot.'/local/ldap/locallib.php');
require_once($CFG->libdir.'/clilib.php');

// Ensure errors are well explained.
set_debugging(DEBUG_DEVELOPER, true);

if ( !is_enabled_auth('cas') && !is_enabled_auth('ldap')) {
    cli_problem('[LOCAL LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
    die;
}

cli_problem('[LOCAL LDAP] The cohort sync cron has been deprecated. Please use the scheduled task instead.');

$plugin = new auth_plugin_cohort();

// Abort execution of the CLI script if the local_ldap\task\group_sync_task is enabled.
$taskdisabled = \core\task\manager::get_scheduled_task('local_ldap\task\attribute_sync_task');
if (!$taskdisabled->get_disabled()) {
    cli_error('[LOCAL LDAP] The scheduled task attributes_sync_task is enabled, the cron execution has been aborted.');
}

$localldap = new local_ldap();
$localldap->sync_cohorts_by_attribute();
