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

$string['attributesynctask'] = 'Synchronisieren von Moodle-Gruppen anhand von LDAP-Attributen';
$string['cohort_synching_ldap_attribute_attribute_desc'] = 'Anpassen des zu suchenden LDAP Benutzer-Attributs (Case-Sensitive)';
$string['cohort_synching_ldap_attribute_attribute'] = 'Zu suchender Attributname';
$string['cohort_synching_ldap_attribute_autocreate_cohorts_desc'] = 'Wenn aktiv werden Moodle-Gruppen automatisch angelegt';
$string['cohort_synching_ldap_attribute_autocreate_cohorts'] = 'Fehlende Moodle-Gruppen anlegen';
$string['cohort_synching_ldap_attribute_idnumbers_desc'] = 'Komma separierte Liste mit anzuwendenen Moodle-Gruppen-ID Nummern; Wenn leer werden alle eindeutigen Werte des Attributs für die synchronisierte Moodle-Gruppe verwendet';
$string['cohort_synching_ldap_attribute_idnumbers'] = 'Gesuchte Moodle-Gruppen-ID Nummer';
$string['cohort_synching_ldap_attribute_objectclass_desc'] = 'Überschreiben der Standard "ObjectClass" Einstellung (Vererbt aus den Authentifizierungsmodulen LDAP oder CAS)';
$string['cohort_synching_ldap_attribute_objectclass'] = 'Benutzer "ObjectClass"';
$string['cohort_synching_ldap_groups_autocreate_cohorts_desc'] = 'Wenn aktiv werden Moodle-Gruppen automatisch angelegt';
$string['cohort_synching_ldap_groups_autocreate_cohorts'] = 'Fehlende Moodle-Gruppen anlegen';
$string['cohort_synchronized_with_attribute'] = 'Moodle-Gruppen synchronisiert mit LDAP-Attribut {$a}';
$string['cohort_synchronized_with_group'] = 'Moodle-Gruppen synchronsiert mit LDAP-Gruppe {$a}';
$string['group_attribute_desc'] = 'Attribut für die Gruppen Bezeichnung im LDAP, normalerweise "cn" ';
$string['group_attribute'] = 'Gruppen Attribut';
$string['group_class_desc'] = 'Angeben einer alternativen "ObjectClass" für die Gruppensuche im LDAP (z.B. group, groupOfNames, etc.)';
$string['group_class'] = 'Gruppen ObjectClass';
$string['groupsynctask'] = 'Synchronisieren von Moodle-Gruppen mit LDAP-Gruppen';
$string['pluginname'] = 'LDAP syncing scripts';
$string['privacy:metadata'] = 'Die "LDAP syncing scripts" speichern keine Daten.';
$string['process_nested_groups_desc'] = 'Wenn aktiviert, werden verschachtelte LDAP-Gruppen verarbeitet.';
$string['process_nested_groups'] = 'Verschachtelte Gruppen verarbeiten';
$string['real_user_attribute_desc'] = 'Wenn das Benutzer-Attribut Großbuchstaben enthält (z.B. sAMAccountName) kann hier die korrekte Schhreibweise angegeben werden, wenne s nicht entsprechend in Moodle\'s CAS/LDAP Auth-Settings hinterlegt ist.';
$string['real_user_attribute'] = 'Korrekte Benutzer "ObjectClass"';
$string['synccohortattribute_info'] = '';
$string['synccohortattribute'] = 'Moodle-Gruppen mit LDAP-Attributen synchronisieren';
$string['synccohortgroup_info'] = '';
$string['synccohortgroup'] = 'Moodle-Gruppen mit LDAP-Gruppen synchronisieren';
$string['group_filter'] = 'LDAP-filter für Gruppen';
$string['group_filter_desc'] = 'LDAP-Filter zum eingrenzen von Gruppen';
