#!/bin/sh
SYNC_DIR="/var/www/html/moodle/local/ldap/cli"
DATE=`date +%Y-%m-%d`
LOG_DIR="/work/moodledata.20/sync"
PHP=/usr/bin/php
if [ ! -d $LOG_DIR ]; then
	mkdir -p $LOG_DIR
fi
OLDPWD=$PWD
cd $SYNC_DIR && \
$PHP -d log_errors=1 -d error_reporting=E_ALL \
-d display_errors=0 -d html_errors=0 -d memory_limit=256M \
./sync_cohorts.php > $LOG_DIR/cohort_ldap_sync_$DATE.txt
cd $OLDPWD

