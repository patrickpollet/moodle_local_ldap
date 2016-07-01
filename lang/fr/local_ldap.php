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
$string['pluginname'] = 'Scripts de synchronisation LDAP';
$string['synccohortgroup'] = 'Syncho des cohortes Moodle avec des groupes LDAP';
$string['synccohortgroup_info'] = 'Permet de synchroniser des cohortes Moodle avec des groupes déclarés dans un annuaire LDAP';
$string['group_attribute'] = 'Attribut du groupe';
$string['group_attribute_desc'] = 'Attribut de nommage des groupes LDAP, normalement cn ';
$string['group_class'] = 'Classe des groupes';
$string['group_class_desc'] = 'peut être group, groupOfNames, groupOfUniqueNames selon les annuaires';
$string['real_user_attribute'] = 'Classe réelle des utilisateurs';
$string['real_user_attribute_desc'] = 'peut être nécessaire si le nom réel de la classe LDAP des utilisateurs contient des majuscules comme insaPerson alors
quelle a été entrée en minuscule dans la configuration CAS ou LDAP de Moodle';
$string['process_nested_groups'] = 'Traiter les groupes imbriqués';
$string['process_nested_groups_desc'] = 'Si cette option est activée, les groupes déclarés comme membres d\'autres groupes seront aussi traités';
$string['debug_ldap_groupes'] = 'Mode verbose';
$string['debug_ldap_groupes_desc'] = 'permet d\'avoir des scripts très bavards lors de la phase de test';
$string['cohort_synching_ldap_groups_autocreate_cohorts'] = 'Créer les cohortes automatiquement';
$string['cohort_synching_ldap_groups_autocreate_cohorts_desc'] = 'Si desactivé, seules les cohortes existantes avec un numéro d\'identification  identique aux noms des groupes LDAP seront synchronisées';
$string['cohort_synchronized_with_group'] = 'Cohorte synchronisée avec le groupe LDAP {$a}';
$string['cohort_synchronized_with_attribute'] = 'Cohorte synchronisée avec l\'attribut LDAP {$a}';
$string['synccohortattribute'] = 'Synchro des cohortes Moodle selon les valeurs d\'un attribut LDAP';
$string['synccohortattribute_info'] = 'Permet de synchroniser des cohortes Moodle selon les valeurs différentes d\'un attribut utilisateur déclaré dans un annuaire LDAP';
$string['cohort_synching_ldap_attribute_attribute'] = 'Nom de l\'attribut ';
$string['cohort_synching_ldap_attribute_attribute_desc'] = 'nom de l\'attribut à utiliser pour determiner les cohortes (respecter la casse) ';
$string['cohort_synching_ldap_attribute_idnumbers'] = 'Identifiants des cohortes à traiter';
$string['cohort_synching_ldap_attribute_idnumbers_desc'] = 'une liste separée par des virgules des numéro d\'identification des cohortes à traiter.
 Si vide, chaque valeur différente de l\'attribut recherché produira une cohorte synchronisée';
$string['cohort_synching_ldap_attribute_objectclass'] = 'Classe utilisateur';
$string['cohort_synching_ldap_attribute_objectclass_desc'] = 'si defini, remplace la valeur de la classe des utilisateurs définie dans la configuration LDAP ou CAS ';
$string['cohort_synching_ldap_attribute_autocreate_cohorts'] = 'Créer les cohortes automatiquement';
$string['cohort_synching_ldap_attribute_autocreate_cohorts_desc'] = 'Si desactivé, seules les cohortes existantes avec un numéro d\'identification  identique aux valeurs de l\'attribut  LDAP seront synchronisées ';
