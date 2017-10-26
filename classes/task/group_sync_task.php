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
 * @package   local_ldap
 * @copyright 2016 Lafayette College ITS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ldap\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/ldap/locallib.php');

class group_sync_task extends \core\task\scheduled_task {
    public function get_name() {
         return get_string('groupsynctask', 'local_ldap');
    }

    public function execute() {
        if ($plugin = new \local_ldap()) {
            $plugin->sync_cohorts_by_group();
        }
    }
}
