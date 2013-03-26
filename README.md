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
* │   │   └── local_ldap.php
* │   └── fr
* │       └── local_ldap.php
* ├── locallib.php
* ├── README.md
* ├── settings.php
* └── version.php

 
Now just visit Site Administration , Notifications to install it. You should get notifications about new settings.
Visit the Wiki page https://github.com/patrickpollet/moodle_local_ldap/wiki for help on parameters. 


usage 
-----

see sample sh scripts in ldap/cli   


