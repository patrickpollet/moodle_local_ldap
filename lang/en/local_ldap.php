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
 * local_ldap version information.
 *
 * @package   local_ldap
 * @copyright 2013 Patrick Pollet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'LDAP synching scripts';
$string['synccohortgroup'] = 'Synch Moodle\'s cohorts with LDAP groups';
$string['synccohortgroup_info'] = '';
$string['group_attribute'] = 'Group attribute';
$string['group_attribute_desc'] = 'Naming attribute of your LDAP groups, usually cn ';
$string['group_class'] = 'Group class';
$string['group_class_desc'] = 'in case your groups are of another class such as group, groupOfNames...';
$string['real_user_attribute'] = 'Real user class';
$string['real_user_attribute_desc'] = 'in case your user_attribute is in mixed case in LDAP (sAMAccountName), but not in Moodle\'s CAS/LDAP settings';
$string['process_nested_groups'] = 'Process nested groups';
$string['process_nested_groups_desc'] = 'If this option is on, LDAP groups included in groups will be processed';
$string['cohort_synching_ldap_groups_autocreate_cohorts'] = 'Autocreate missing cohorts';
$string['cohort_synching_ldap_groups_autocreate_cohorts_desc'] = 'if false will not create missing cohorts (admin must create them before) ';
$string['cohort_synchronized_with_group'] = 'Cohort synchronized with LDAP group {$a}';
$string['cohort_synchronized_with_attribute'] = 'Cohort synchronized with LDAP attribute {$a}';
$string['synccohortattribute'] = 'Synch Moodle\'s cohorts with LDAP attribute';
$string['synccohortattribute_info'] = '';
$string['cohort_synching_ldap_attribute_attribute'] = 'Attribute name to search';
$string['cohort_synching_ldap_attribute_attribute_desc'] = 'adjust to the LDAP user\'s attribute to search for (respect case)';
$string['cohort_synching_ldap_attribute_idnumbers'] = 'Target cohorts idnumbers';
$string['cohort_synching_ldap_attribute_idnumbers_desc'] = 'a comma separated list of target cohorts idnumbers ; if missing ALL distinct values of the attribute will produce a synched cohort';
$string['cohort_synching_ldap_attribute_objectclass'] = 'User class';
$string['cohort_synching_ldap_attribute_objectclass_desc'] = 'if set override default value inherited from LDAP or CAS auth plugin (respect case)';
$string['cohort_synching_ldap_attribute_autocreate_cohorts'] = 'Autocreate missing cohorts';
$string['cohort_synching_ldap_attribute_autocreate_cohorts_desc'] = 'if false will not create missing cohorts (admin must create them before) ';
$string['attributesynctask'] = 'Synchronize cohorts from LDAP attributes';
$string['groupsynctask'] = 'Synchronize cohorts from LDAP groups';
