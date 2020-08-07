# Changelog

## Unreleased

- Change default branch to "main"
- Update CI tool to version 3
- Dropped support for Moodle 3.6

## 3.6.0 (June 15, 2020)

- Code cleanup
- Streamlined unit testing matrix
- Dropped support for Moodle 3.4 and 3.5

## 3.4.2 (May 19, 2019)

- Minor code cleanup and internal documentation fixes

## 3.4.1 (September 7, 2018)

- Fixed bug where attribute syncing could fail in large Active Directory environments
- Fixed bug where group syncing could fail in large Active Directory environments
- Updated tests to use large data sets
- Added optional unit test support for Active Directory

## 3.4.0 (May 4, 2018)

- Updated for GDPR compliance
- Fixed bug where parentheses were not filtered correctly (thanks to [@cperves](https://github.com/cperves) for the report)

## 3.3.0 (August 9, 2017)

- Changed version numbering to match stable version
- Bugfix for [MDL-57558](https://tracker.moodle.org/browse/MDL-57558): attribute sync was broken by Moodle 3.3.1

## 2.0.1 (April 24, 2017)

- Updated tests to support [MDL-12689](https://tracker.moodle.org/browse/MDL-12689)

## 2.0.0 (July 15, 2015)

- Official support for Moodle 2.9-Moodle 3.1
- Migrated CLI script to scheduled task
- Unit test coverage for OpenLDAP
