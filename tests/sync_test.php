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

class local_ldap_sync_testcase extends advanced_testcase {

    public function test_cohort_group_sync() {
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
        $usertype = 'rfc2307';
        $connection = $this->connect_to_ldap($usertype);

        $this->enable_plugin();

        // Create new empty test container.
        $topdn = 'dc=moodletest,'.TEST_AUTH_LDAP_DOMAIN;
        $this->recursive_delete(TEST_AUTH_LDAP_DOMAIN, 'dc=moodletest', $usertype);

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
        $departments = array('english', 'history', 'english(bis)');
        foreach ($departments as $department) {
            $o = array();
            $o['objectClass'] = array('groupOfNames');
            $o['cn']          = $department;
            $o['member']      = array('cn=username1,ou=users,'.$topdn, 'cn=username2,ou=users,'.$topdn,
                    'cn=username5,ou=users,'.$topdn);
            ldap_add($connection, 'cn='.$o['cn'].',ou=groups,'.$topdn, $o);
        }

        // Configure the authentication plugin a bit.
        set_config('host_url', TEST_AUTH_LDAP_HOST_URL, 'auth_ldap');
        set_config('start_tls', 0, 'auth_ldap');
        set_config('ldap_version', 3, 'auth_ldap');
        set_config('ldapencoding', 'utf-8', 'auth_ldap');
        set_config('pagesize', '2', 'auth_ldap');
        set_config('bind_dn', TEST_AUTH_LDAP_BIND_DN, 'auth_ldap');
        set_config('bind_pw', TEST_AUTH_LDAP_BIND_PW, 'auth_ldap');
        set_config('user_type', 'rfc2307', 'auth_ldap');
        set_config('contexts', 'ou=users,'.$topdn.';ou=groups,'.$topdn, 'auth_ldap');
        set_config('search_sub', 0, 'auth_ldap');
        set_config('opt_deref', LDAP_DEREF_NEVER, 'auth_ldap');
        set_config('user_attribute', 'cn', 'auth_ldap');
        set_config('memberattribute', 'member', 'auth_ldap');
        set_config('memberattribute_isdn', 0, 'auth_ldap');
        set_config('creators', '', 'auth_ldap');
        set_config('removeuser', AUTH_REMOVEUSER_KEEP, 'auth_ldap');
        set_config('field_map_email', 'mail', 'auth_ldap');
        set_config('field_updatelocal_email', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_email', '0', 'auth_ldap');
        set_config('field_lock_email', 'unlocked', 'auth_ldap');
        set_config('field_map_firstname', 'givenName', 'auth_ldap');
        set_config('field_updatelocal_firstname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_firstname', '0', 'auth_ldap');
        set_config('field_lock_firstname', 'unlocked', 'auth_ldap');
        set_config('field_map_lastname', 'sn', 'auth_ldap');
        set_config('field_updatelocal_lastname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_lastname', '0', 'auth_ldap');
        set_config('field_lock_lastname', 'unlocked', 'auth_ldap');
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
        $cohort = new stdClass();
        $cohort->contextid = context_system::instance()->id;
        $cohort->name = "English Department (bis)";
        $cohort->idnumber = 'english(bis)';
        $englishbisid = cohort_add_cohort($cohort);

        // All three cohorts should have three members.
        $plugin = new local_ldap();
        $plugin->sync_cohorts_by_group();
        $members = $DB->count_records('cohort_members', array('cohortid' => $historyid));
        $this->assertEquals(3, $members);
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishid));
        $this->assertEquals(3, $members);
        $members = $DB->count_records('cohort_members', array('cohortid' => $englishbisid));
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
        $this->recursive_delete(TEST_AUTH_LDAP_DOMAIN, 'dc=moodletest', $usertype);
    }

    public function test_cohort_attribute_sync() {
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

        $usertype = 'rfc2307';
        $connection = $this->connect_to_ldap($usertype);

        $this->enable_plugin();

        // Create new empty test container.
        $topdn = 'dc=moodletest,'.TEST_AUTH_LDAP_DOMAIN;
        $this->recursive_delete(TEST_AUTH_LDAP_DOMAIN, 'dc=moodletest', $usertype);

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
            ldap_mod_add($connection, "cn=username$i,ou=users,$topdn",
                array('objectClass' => 'eduPerson'));
        }

        // Set some attributes.
        ldap_mod_add($connection, "cn=username1,ou=users,$topdn",
            array('eduPersonAffiliation' => 'faculty'));
        ldap_mod_add($connection, "cn=username2,ou=users,$topdn",
            array('eduPersonAffiliation' => 'faculty'));
        ldap_mod_add($connection, "cn=username3,ou=users,$topdn",
            array('eduPersonAffiliation' => 'staff'));
        ldap_mod_add($connection, "cn=username4,ou=users,$topdn",
            array('eduPersonAffiliation' => 'staff'));
        ldap_mod_add($connection, "cn=username5,ou=users,$topdn",
            array('eduPersonAffiliation' => 'staff(pt)'));

        // Configure the authentication plugin a bit.
        set_config('host_url', TEST_AUTH_LDAP_HOST_URL, 'auth_ldap');
        set_config('start_tls', 0, 'auth_ldap');
        set_config('ldap_version', 3, 'auth_ldap');
        set_config('ldapencoding', 'utf-8', 'auth_ldap');
        set_config('pagesize', '2', 'auth_ldap');
        set_config('bind_dn', TEST_AUTH_LDAP_BIND_DN, 'auth_ldap');
        set_config('bind_pw', TEST_AUTH_LDAP_BIND_PW, 'auth_ldap');
        set_config('user_type', 'rfc2307', 'auth_ldap');
        set_config('contexts', 'ou=users,'.$topdn, 'auth_ldap');
        set_config('search_sub', 0, 'auth_ldap');
        set_config('opt_deref', LDAP_DEREF_NEVER, 'auth_ldap');
        set_config('user_attribute', 'cn', 'auth_ldap');
        set_config('memberattribute', 'member', 'auth_ldap');
        set_config('memberattribute_isdn', 0, 'auth_ldap');
        set_config('creators', '', 'auth_ldap');
        set_config('removeuser', AUTH_REMOVEUSER_KEEP, 'auth_ldap');
        set_config('field_map_email', 'mail', 'auth_ldap');
        set_config('field_updatelocal_email', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_email', '0', 'auth_ldap');
        set_config('field_lock_email', 'unlocked', 'auth_ldap');
        set_config('field_map_firstname', 'givenName', 'auth_ldap');
        set_config('field_updatelocal_firstname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_firstname', '0', 'auth_ldap');
        set_config('field_lock_firstname', 'unlocked', 'auth_ldap');
        set_config('field_map_lastname', 'sn', 'auth_ldap');
        set_config('field_updatelocal_lastname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_lastname', '0', 'auth_ldap');
        set_config('field_lock_lastname', 'unlocked', 'auth_ldap');
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
        $cohort->name = "All faculty";
        $cohort->idnumber = 'faculty';
        $facultyid = cohort_add_cohort($cohort);
        $cohort = new stdClass();
        $cohort->contextid = context_system::instance()->id;
        $cohort->name = "All staff";
        $cohort->idnumber = 'staff';
        $staffid = cohort_add_cohort($cohort);
        $cohort = new stdClass();
        $cohort->contextid = context_system::instance()->id;
        $cohort->name = "All staff (pt)";
        $cohort->idnumber = 'staff(pt)';
        $staffptid = cohort_add_cohort($cohort);

        // Faculty and staff should have two members and staff(pt) should have one.
        $plugin = new local_ldap();
        $plugin->sync_cohorts_by_attribute();
        $members = $DB->count_records('cohort_members', array('cohortid' => $facultyid));
        $this->assertEquals(2, $members);
        $members = $DB->count_records('cohort_members', array('cohortid' => $staffid));
        $this->assertEquals(2, $members);
        $members = $DB->count_records('cohort_members', array('cohortid' => $staffptid));
        $this->assertEquals(1, $members);

        // Remove a user and then ensure he's re-added.
        $members = $plugin->get_cohort_members($staffid);
        cohort_remove_member($staffid, current($members)->id);
        $members = $DB->count_records('cohort_members', array('cohortid' => $staffid));
        $this->assertEquals(1, $members);
        $plugin->sync_cohorts_by_attribute();
        $members = $DB->count_records('cohort_members', array('cohortid' => $staffid));
        $this->assertEquals(2, $members);

        // Add an affiliation in LDAP and ensure he'd added.
        ldap_mod_add($connection, "cn=username3,ou=users,$topdn",
            array('eduPersonAffiliation' => 'faculty'));
        $members = $DB->count_records('cohort_members', array('cohortid' => $facultyid));
        $this->assertEquals(2, $members);
        $plugin->sync_cohorts_by_attribute();
        $members = $DB->count_records('cohort_members', array('cohortid' => $facultyid));
        $this->assertEquals(3, $members);

        // Remove a user from a group in LDAP and ensure he's deleted.
        ldap_mod_del($connection, "cn=username3,ou=users,$topdn",
            array('eduPersonAffiliation' => 'staff'));
        $members = $DB->count_records('cohort_members', array('cohortid' => $staffid));
        $this->assertEquals(2, $members);
        $plugin->sync_cohorts_by_attribute();
        $members = $DB->count_records('cohort_members', array('cohortid' => $staffid));
        $this->assertEquals(1, $members);

        // Cleanup.
        $this->recursive_delete(TEST_AUTH_LDAP_DOMAIN, 'dc=moodletest', $usertype);
    }

    // Create an ldap user. From auth_ldap.
    protected function create_ldap_user($connection, $topdn, $i) {
        $o = array();
        $o['objectClass']   = array('inetOrgPerson', 'organizationalPerson', 'person', 'posixAccount');
        $o['cn']            = 'username'.$i;
        $o['sn']            = 'Lastname'.$i;
        $o['givenName']     = 'Firstname'.$i;
        $o['uid']           = $o['cn'];
        $o['uidnumber']     = 2000 + $i;
        $o['gidNumber']     = 1000 + $i;
        $o['homeDirectory'] = '/';
        $o['mail']          = 'user'.$i.'@example.com';
        $o['userPassword']  = 'pass'.$i;
        ldap_add($connection, 'cn='.$o['cn'].',ou=users,'.$topdn, $o);
    }

    // Delete a user from ldap. From auth_ldap.
    protected function delete_ldap_user($connection, $topdn, $i) {
        ldap_delete($connection, 'cn=username'.$i.',ou=users,'.$topdn);
    }

    // Activate the ldap authentication plugin. From auth_ldap.
    protected function enable_plugin() {
        $auths = get_enabled_auth_plugins(true);
        if (!in_array('ldap', $auths)) {
            $auths[] = 'ldap';

        }
        set_config('auth', implode(',', $auths));
    }

    /**
     * Connect to the LDAP server.
     *
     * @param $usertype The configured user type for this connection.
     *
     * @return resource
     */
    protected function connect_to_ldap($usertype) {
        $debuginfo = '';
        if (!$connection = ldap_connect_moodle(TEST_AUTH_LDAP_HOST_URL, 3, $usertype, TEST_AUTH_LDAP_BIND_DN,
                TEST_AUTH_LDAP_BIND_PW, LDAP_DEREF_NEVER, $debuginfo, false)) {
            $this->markTestSkipped('Can not connect to LDAP test server: '.$debuginfo);
            return false;
        }
        return $connection;
    }

    /**
     * Clear out the test environment. We create a separate connection in case
     * pagination is required.
     *
     * @param string $dn The top level distinguished name
     * @param string $filter LDAP filter.
     * @param string $usertype The configured user type for this connection.
     */
    protected function recursive_delete($dn, $filter, $usertype) {
        $ldapconnection = $this->connect_to_ldap($usertype);

        if ($res = ldap_list($ldapconnection, $dn, $filter, array('dn'))) {
            $info = ldap_get_entries($ldapconnection, $res);

            if ($info['count'] > 0) {
                $ldappagedresults = ldap_paged_results_supported(3, $ldapconnection);
                $ldapcookie = '';
                $todelete = array();
                do {
                    if ($ldappagedresults) {
                        ldap_control_paged_result($ldapconnection, 250, true, $ldapcookie);
                    }
                    $res = ldap_search($ldapconnection, "$filter,$dn", 'cn=*', array('dn'));
                    if (!$res) {
                        continue;
                    }
                    $info = ldap_get_entries($ldapconnection, $res);
                    foreach ($info as $i) {
                        if (isset($i['dn'])) {
                            $todelete[] = $i['dn'];
                        }
                    }
                    if ($ldappagedresults) {
                        ldap_control_paged_result_response($ldapconnection, $res, $ldapcookie);
                    }
                    ldap_free_result($res);
                } while ($ldappagedresults && $ldapcookie !== null && $ldapcookie != '');

                if ($ldappagedresults) {
                    ldap_close($ldapconnection);
                    unset($ldapconnection);
                    $ldapconnection = $this->connect_to_ldap($usertype);
                }
                if (is_array($todelete)) {
                    foreach ($todelete as $delete) {
                        ldap_delete($ldapconnection, $delete);
                    }
                }
                $todelete = array();

                do {
                    if ($ldappagedresults) {
                        ldap_control_paged_result($ldapconnection, 250, true, $ldapcookie);
                    }
                    $res = ldap_search($ldapconnection, "$filter,$dn", 'ou=*', array('dn'));
                    if (!$res) {
                        continue;
                    }
                    $info = ldap_get_entries($ldapconnection, $res);
                    foreach ($info as $i) {
                        if (isset($i['dn']) and $info[0]['dn'] != $i['dn']) {
                            $todelete[] = $i['dn'];
                        }
                    }
                    if ($ldappagedresults) {
                        ldap_control_paged_result_response($ldapconnection, $res, $ldapcookie);
                    }
                    ldap_free_result($res);
                } while ($ldappagedresults && $ldapcookie !== null && $ldapcookie != '');

                if ($ldappagedresults) {
                    ldap_close($ldapconnection);
                    unset($ldapconnection);
                    $ldapconnection = $this->connect_to_ldap($usertype);
                }

                if (is_array($todelete)) {
                    foreach ($todelete as $delete) {
                        ldap_delete($ldapconnection, $delete);
                    }
                }

                ldap_delete($ldapconnection, "$filter,$dn");
            }
        }
        ldap_close($ldapconnection);
        unset($ldapconnection);
    }
}
