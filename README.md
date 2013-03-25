moodle_local_ldap
=================

Various synchronization scripts between Moodle and LDAP directories (see https://tracker.moodle.org/browse/MDL-25011 
and https://tracker.moodle.org/browse/MDL-25054 )


Better documentation in progress in the wiki https://github.com/patrickpollet/moodle_local_ldap/wiki

installation via git 
--------------------

  cd /var/www/moodle
  
  git clone git@github.com:patrickpollet/moodle_local_ldap.git local/ldap
  
  echo 'local/ldap' >> .git/info/exclude
  
  
installation via zip 
--------------------
 
  collect a zip file from this github repository
  
  cd /var/www/moodle/local
  
  md ldap
  
  unzip the zip file in the ldap directory
  
   
   
In both case you should have the following structure in local/ldap directory

 * ldap/
 * ├── cli
 * │   ├── sync_cohorts_attribute.php
 * │   ├── sync_cohorts.php
 * │   ├── sync_moodle_cohorts_2.sh
 * │   └── sync_moodle_cohorts.sh
 * ├── db
 * ├── gitinit.txt
 * ├── lang
 * │   ├── en
 * │   └── fr
 * └── README.md
 

setup for synching Moodle cohorts with LDAP groups
--------------------------------------------------

/**
 * CONFIGURATION
 * this script make use of current Moodle's LDAP/CAS settings 
 * user_attribute 
 * member_attribute
 * member_attribute_isdn
 * objectclass
 * 
 * and the following default values that can be altered in Moodle's
 * config.php file
 * $CFG->ldap_group_attribute='cn';          // in case your groups are not cn=xxx,
 * $CFG->ldap_group_class='groupofuniquenames'; // in case your groups are of class group ...
 * $CFG->ldap_real_user_attribute='uid';     // in case your user_attribute is in mixed case in LDAP (sAMAccountName) and not in Moodle
 * $CFG->ldap_process_nested_groups=0;       // turn on nested groups
 * $CFG->debug_ldap_groupes=false;           // turn on extensive debug upon running
 * $CFG->cohort_synching_ldap_groups_autocreate_cohorts // if false will not create missing cohorts (admin must create them before) 
 * 
 * 
 */
 
 
setup for synching Moodle cohorts with all values of some LDAP attribute
-----------------------------------------------------------------------

/**
 * CONFIGURATION
 * this script make use of current Moodle's LDAP/CAS settings 
 * user_attribute 
 * objectclass
 * 
 * and the following default values that can be altered in Moodle's
 * config.php file
 * $CFG->cohort_synching_ldap_attribute_attribute='eduPersonAffiliation';     // adjust to the attribute to search for 
 * $CFG->cohort_synching_ldap_attribute_idnumbers='comma separated list of target cohorts idnumbers'; // if missing ALL distinct values of the attribute will produce a synched cohort
 * $CFG->cohort_synching_ldap_attribute_verbose=false;           // turn on extensive debug upon running
 * $CFG->cohort_synching_ldap_attribute_objectclass ; // if set override default value inherited from LDAP auth plugin (CAUTION respect the case !)
 * $CFG->cohort_synching_ldap_attribute_autocreate_cohorts // if false will not create missing cohorts (admin must create them before) 
 * 
 */
 
   
  
  

usage 
-----

see sample sh scripts in ldap/cli   


