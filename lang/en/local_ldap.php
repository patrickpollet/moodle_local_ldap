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
 * local_ldap language strings.
 *
 * @package   local_ldap
 * @copyright 2013 Patrick Pollet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['attributesynctask'] = 'Synchronize cohorts from LDAP attributes';
$string['cohort_synching_ldap_attribute_attribute_desc'] = 'Adjust to the LDAP user\'s attribute to search for (respect case)';
$string['cohort_synching_ldap_attribute_attribute'] = 'Attribute name to search';
$string['cohort_synching_ldap_attribute_autocreate_cohorts_desc'] = 'If selected will create missing cohorts automatically';
$string['cohort_synching_ldap_attribute_autocreate_cohorts'] = 'Autocreate missing cohorts';
$string['cohort_synching_ldap_attribute_idnumbers_desc'] = 'A comma-separated list of target cohort idnumbers; if missing all distinct values of the attribute will produce a synced cohort';
$string['cohort_synching_ldap_attribute_idnumbers'] = 'Target cohorts idnumbers';
$string['cohort_synching_ldap_attribute_objectclass_desc'] = 'Use to override default value inherited from LDAP or CAS auth plugin (respect case)';
$string['cohort_synching_ldap_attribute_objectclass'] = 'User class';
$string['cohort_synching_ldap_groups_autocreate_cohorts_desc'] = 'If selected will create missing cohorts automatically';
$string['cohort_synching_ldap_groups_autocreate_cohorts'] = 'Autocreate missing cohorts';
$string['cohort_synchronized_with_attribute'] = 'Cohort synchronized with LDAP attribute {$a}';
$string['cohort_synchronized_with_group'] = 'Cohort synchronized with LDAP group {$a}';
$string['group_attribute_desc'] = 'Naming attribute of your LDAP groups, usually cn ';
$string['group_attribute'] = 'Group attribute';
$string['group_class_desc'] = 'Set if your groups are of another class such as group, groupOfNames...';
$string['group_class'] = 'Group class';
$string['groupsynctask'] = 'Synchronize cohorts from LDAP groups';
$string['pluginname'] = 'LDAP syncing scripts';
$string['privacy:metadata'] = 'The LDAP syncing scripts do not store any data.';
$string['process_nested_groups_desc'] = 'If selected, LDAP groups included in groups will be processed';
$string['process_nested_groups'] = 'Process nested groups';
$string['real_user_attribute_desc'] = 'Use if your user_attribute is in mixed case in LDAP (sAMAccountName), but not in Moodle\'s CAS/LDAP settings';
$string['real_user_attribute'] = 'Real user class';
$string['synccohortattribute_info'] = '';
$string['synccohortattribute'] = 'Sync Moodle\'s cohorts with LDAP attribute';
$string['synccohortgroup_info'] = '';
$string['synccohortgroup'] = 'Sync Moodle\'s cohorts with LDAP groups';
