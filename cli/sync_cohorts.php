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
 * group_attribute='cn';          // in case your groups are not cn=xxx,
 * group_class='groupofuniquenames'; // in case your groups are of class group ...
 * real_user_attribute='uid';     // in case your user_attribute is in mixed case in LDAP (sAMAccountName)
 * process_nested_groups=0;       // turn on nested groups
 * cohort_synching_ldap_groups_autocreate_cohorts // if false will not create missing cohorts (admin must create them before)
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

// Abort execution of the CLI script if the local_ldap\task\group_sync_task is enabled.
$taskdisabled = \core\task\manager::get_scheduled_task('local_ldap\task\group_sync_task');
if (!$taskdisabled->get_disabled()) {
    cli_error('[LOCAL LDAP] The scheduled task group_sync_task is enabled, the cron execution has been aborted.');
}

$localldap = new local_ldap();
$localldap->sync_cohorts_by_group();
