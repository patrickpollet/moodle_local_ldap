# Changelog

## Unreleased

- Fixed bug where attribute syncing could fail in large Active Directory environments
- Fixed bug where group syncing could fail in large Active Directory environments
- Updated tests to use large data sets
- Added optional unit test support for Active Directory

## 3.4.0 - 2018-05-04

- Updated for GDPR compliance
- Fixed bug where parentheses were not filtered correctly (thanks to [@cperves](https://github.com/cperves) for the report)

## 3.3.0 - 2017-08-09

- Changed version numbering to match stable version
- Bugfix for [MDL-57558](https://tracker.moodle.org/browse/MDL-57558): attribute sync was broken by Moodle 3.3.1

## 2.0.1 - 2017-04-24

- Updated tests to support [MDL-12689](https://tracker.moodle.org/browse/MDL-12689)

## 2.0.0 - 2016-07-15

- Official support for Moodle 2.9-Moodle 3.1
- Migrated CLI script to scheduled task
- Unit test coverage for OpenLDAP
