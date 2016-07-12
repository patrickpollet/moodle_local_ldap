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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/local/ldap/locallib.php');
require_once($CFG->dirroot.'/auth/ldap/tests/plugin_test.php');

class local_ldap_sync_testcase extends auth_ldap_plugin_testcase {
    public function test_auth_ldap() {
        global $CFG, $DB;

        if (!extension_loaded('ldap')) {
            $this->markTestSkipped('LDAP extension is not loaded.');
        }

        $this->resetAfterTest();

        require_once($CFG->dirroot.'/auth/ldap/auth.php');
        require_once($CFG->libdir.'/ldaplib.php');

        if (!defined('TEST_AUTH_LDAP_HOST_URL') or !defined('TEST_AUTH_LDAP_BIND_DN') or !defined('TEST_AUTH_LDAP_BIND_PW')
                or !defined('TEST_AUTH_LDAP_DOMAIN')) {
            $this->markTestSkipped('External LDAP test server not configured.');
        }

        // Make sure we can connect the server.
        $debuginfo = '';
        if (!$connection = ldap_connect_moodle(TEST_AUTH_LDAP_HOST_URL, 3, 'rfc2307', TEST_AUTH_LDAP_BIND_DN,
                TEST_AUTH_LDAP_BIND_PW, LDAP_DEREF_NEVER, $debuginfo, false)) {
            $this->markTestSkipped('Can not connect to LDAP test server: '.$debuginfo);
        }

        $this->enable_plugin();

        // Create new empty test container.
        $topdn = 'dc=moodletest,'.TEST_AUTH_LDAP_DOMAIN;
        $this->recursive_delete($connection, TEST_AUTH_LDAP_DOMAIN, 'dc=moodletest');
        $o = array();
        $o['objectClass'] = array('dcObject', 'organizationalUnit');
        $o['dc']         = 'moodletest';
        $o['ou']         = 'MOODLETEST';
        if (!ldap_add($connection, 'dc=moodletest,'.TEST_AUTH_LDAP_DOMAIN, $o)) {
            $this->markTestSkipped('Can not create test LDAP container.');
        }

        // Create a few users.
        $o = array();
        $o['objectClass'] = array('organizationalUnit');
        $o['ou']          = 'users';
        ldap_add($connection, 'ou='.$o['ou'].','.$topdn, $o);
        for ($i = 1; $i <= 5; $i++) {
            $this->create_ldap_user($connection, $topdn, $i);
        }

        // Create department groups.
        $o = array();
        $o['objectClass'] = array('organizationalUnit');
        $o['ou']          = 'groups';
        ldap_add($connection, 'ou='.$o['ou'].','.$topdn, $o);
        $departments = array('english', 'history');
        foreach ($departments as $department) {
            $o = array();
            $o['objectClass'] = array('groupOfNames');
            $o['cn']          = $department;
            $o['member']      = array('cn=username1,ou=users,'.$topdn, 'cn=username2,ou=users,'.$topdn,
                    'cn=username5,ou=users,'.$topdn);
            ldap_add($connection, 'cn='.$o['cn'].',ou=groups,'.$topdn, $o);
        }

        // Configure the authentication plugin a bit.
        set_config('host_url', TEST_AUTH_LDAP_HOST_URL, 'auth/ldap');
        set_config('start_tls', 0, 'auth/ldap');
        set_config('ldap_version', 3, 'auth/ldap');
        set_config('ldapencoding', 'utf-8', 'auth/ldap');
        set_config('pagesize', '2', 'auth/ldap');
        set_config('bind_dn', TEST_AUTH_LDAP_BIND_DN, 'auth/ldap');
        set_config('bind_pw', TEST_AUTH_LDAP_BIND_PW, 'auth/ldap');
        set_config('user_type', 'rfc2307', 'auth/ldap');
        set_config('contexts', 'ou=users,'.$topdn.';ou=groups,'.$topdn, 'auth/ldap');
        set_config('search_sub', 0, 'auth/ldap');
        set_config('opt_deref', LDAP_DEREF_NEVER, 'auth/ldap');
        set_config('user_attribute', 'cn', 'auth/ldap');
        set_config('memberattribute', 'member', 'auth/ldap');
        set_config('memberattribute_isdn', 0, 'auth/ldap');
        set_config('creators', '', 'auth/ldap');
        set_config('removeuser', AUTH_REMOVEUSER_KEEP, 'auth/ldap');
        set_config('field_map_email', 'mail', 'auth/ldap');
        set_config('field_updatelocal_email', 'oncreate', 'auth/ldap');
        set_config('field_updateremote_email', '0', 'auth/ldap');
        set_config('field_lock_email', 'unlocked', 'auth/ldap');
        set_config('field_map_firstname', 'givenName', 'auth/ldap');
        set_config('field_updatelocal_firstname', 'oncreate', 'auth/ldap');
        set_config('field_updateremote_firstname', '0', 'auth/ldap');
        set_config('field_lock_firstname', 'unlocked', 'auth/ldap');
        set_config('field_map_lastname', 'sn', 'auth/ldap');
        set_config('field_updatelocal_lastname', 'oncreate', 'auth/ldap');
        set_config('field_updateremote_lastname', '0', 'auth/ldap');
        set_config('field_lock_lastname', 'unlocked', 'auth/ldap');
        $this->assertEquals(2, $DB->count_records('user'));

        // Sync the users.
        $auth = get_auth_plugin('ldap');

        ob_start();
        $sink = $this->redirectEvents();
        $auth->sync_users(true);
        $events = $sink->get_events();
        $sink->close();
        ob_end_clean();

        // Check events, 5 users created.
        $this->assertCount(5, $events);

        // Add the cohorts.
        $cohort = new stdClass();
        $cohort->contextid = context_system::instance()->id;
        $cohort->name = "History Department";
        $cohort->idnumber = 'history';
        $historyid = cohort_add_cohort($cohort);
        $cohort = new stdClass();
        $cohort->contextid = context_system::instance()->id;
        $cohort->name = "English Department";
        $cohort->idnumber = 'english';
        $englishid = cohort_add_cohort($cohort);

        // Both cohorts should have three members.
        $plugin = new local_ldap();
        $plugin->sync_cohorts_by_group();
        $members = $DB->count_records('cohort_members', array('cohortid' => $historyid));
        $this->assertEquals(3, $members);
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishid));
        $this->assertEquals(3, $members);

        // Remove a user and then ensure he's re-added.
        $members = $plugin->get_cohort_members($englishid);
        cohort_remove_member($englishid, current($members)->id);
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishid));
        $this->assertEquals(2, $members);
        $plugin->sync_cohorts_by_group();
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishid));
        $this->assertEquals(3, $members);

        // Add a user to a group in LDAP and ensure he'd added.
        ldap_mod_add($connection, "cn=history,ou=groups,$topdn",
            array($auth->config->memberattribute => "cn=username3,ou=users,$topdn"));
        $members = $DB->count_records('cohort_members', array('cohortid' => $historyid));
        $this->assertEquals(3, $members);
        $plugin->sync_cohorts_by_group();
        $members = $DB->count_records('cohort_members', array('cohortid' => $historyid));
        $this->assertEquals(4, $members);

        // Remove a user from a group in LDAP and ensure he's deleted.
        ldap_mod_del($connection, "cn=english,ou=groups,$topdn",
            array($auth->config->memberattribute => "cn=username2,ou=users,$topdn"));
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishid));
        $this->assertEquals(3, $members);
        $plugin->sync_cohorts_by_group();
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishid));
        $this->assertEquals(2, $members);

        // Cleanup.
        $this->recursive_delete($connection, TEST_AUTH_LDAP_DOMAIN, 'dc=moodletest');
        ldap_close($connection);
    }
}
