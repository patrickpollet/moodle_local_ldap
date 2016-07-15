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
 * Cohort sync with LDAP attribute script. This is deprecated.
 *
 * @package    local_ldap
 * @copyright  2010 Patrick Pollet - based on code by Jeremy Guittirez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
