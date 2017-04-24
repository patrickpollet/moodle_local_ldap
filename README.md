LDAP synching scripts
=====================

[![Build Status](https://api.travis-ci.org/mackensen/moodle_local_ldap.png)](https://api.travis-ci.org/mackensen/moodle_local_ldap)

This plugin synchronizes Moodle cohorts against an LDAP directory using either group memberships or attribute values. This is a continuation of Patrick Pollet's [local_ldap](https://github.com/patrickpollet/moodle_local_ldap) plugin, which in turn was inspired by [MDL-25011](https://tracker.moodle.org/browse/MDL-25011) and [MDL-25054](https://tracker.moodle.org/browse/MDL-25054).

Requirements
------------
- Moodle 3.3 (build 2017042100.00 or later)

Various synchronization scripts between Moodle cohorts and LDAP directories (see  
and  )

Installation
------------
Copy the ldap folder into your /local directory and visit your Admin Notification page to complete the installation. You must have either the CAS or LDAP authentication method enabled.

Configuration
-------------
Depending on your environment the plugin may work with default options. Configuration settings include the group class (`groupOfNames` by default) and whether to automatically import all found LDAP groups as cohorts. By default this setting is disabled.

Usage
-----
Previous versions of this plugin used a CLI script. This is deprecated in favor of two [Scheduled tasks](https://docs.moodle.org/31/en/Scheduled_tasks), one for syncing by group and another for syncing by attribute. Both are configured to run hourly and are disabled by default.

Author
-----
- Charles Fulton (fultonc@lafayette.edu)
- Patrick Pollet
