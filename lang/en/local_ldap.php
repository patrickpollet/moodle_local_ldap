<?php

$string['pluginname'] = 'LDAP synching scripts';


$string['synccohortgroup']='Synch Moodle\'s cohorts with LDAP groups';
$string['synccohortgroup_info']='';

$string['ldap_group_attribute']='Group attribute';
$string['ldap_group_attribute_desc']='Naming attribute of your LDAP groups, usually cn ';

$string['ldap_group_class']='Group class';
$string['ldap_group_class_desc']='in case your groups are of another class such as group, groupOfNames...';

$string['ldap_real_user_attribute']='Real user class';
$string['ldap_real_user_attribute_desc']='in case your user_attribute is in mixed case in LDAP (sAMAccountName), but not in Moodle\'s CAS/LDAP settings';

$string['ldap_process_nested_groups']='Process nested groups';
$string['ldap_process_nested_groups_desc']='If this option is on, LDAP groups included in groups will be processed';

$string['debug_ldap_groupes']='Verbose mode';
$string['debug_ldap_groupes_desc']='Turn on or off the verbose mode when running the script';

$string['cohort_synching_ldap_groups_autocreate_cohorts']='Autocreate missing cohorts';
$string['cohort_synching_ldap_groups_autocreate_cohorts_desc']='if false will not create missing cohorts (admin must create them before) ';

$string['cohort_synchronized_with_group']='Cohort synchronized with LDAP group {$a}';
$string['cohort_synchronized_with_attribute']='Cohort synchronized with LDAP attribute {$a}';

$string['synccohortattribute']='Synch Moodle\'s cohorts with LDAP attribute';
$string['synccohortattribute_info']='';

$string['cohort_synching_ldap_attribute_attribute']='Attribute name to search';
$string['cohort_synching_ldap_attribute_attribute_desc']='adjust to the LDAP user\'s attribute to search for ';

$string['cohort_synching_ldap_attribute_idnumbers']='Target cohorts idnumbers';
$string['cohort_synching_ldap_attribute_idnumbers_desc']='a comma separated list of target cohorts idnumbers ; if missing ALL distinct values of the attribute will produce a synched cohort';

$string['cohort_synching_ldap_attribute_objectclass']='User class';
$string['cohort_synching_ldap_attribute_objectclass_desc']='if set override default value inherited from LDAP or CAS auth plugin ';

$string['cohort_synching_ldap_attribute_autocreate_cohorts']='Autocreate missing cohorts';
$string['cohort_synching_ldap_attribute_autocreate_cohorts_desc']='if false will not create missing cohorts (admin must create them before) ';





?>
