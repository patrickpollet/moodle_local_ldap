<?php

$string['pluginname'] = 'Scripts de synchronisation LDAP';


$string['synccohortgroup']='Syncho des cohortes Moodle avec des groupes LDAP';
$string['synccohortgroup_info']='Permet de synchroniser des cohortes Moodle avec des groupes déclarés dans un annuaire LDAP';

$string['group_attribute']='Attribut du groupe';
$string['group_attribute_desc']='Attribut de nommage des groupes LDAP, normalement cn ';

$string['group_class']='Classe des groupes';
$string['group_class_desc']='peut être group, groupOfNames, groupOfUniqueNames selon les annuaires';

$string['real_user_attribute']='Classe réelle des utilisateurs';
$string['real_user_attribute_desc']='peut être nécessaire si le nom réel de la classe LDAP des utilisateurs contient des majuscules comme insaPerson alors
quelle a été entrée en minuscule dans la configuration CAS ou LDAP de Moodle';

$string['process_nested_groups']='Traiter les groupes imbriqués';
$string['process_nested_groups_desc']='Si cette option est activée, les groupes déclarés comme membres d\'autres groupes seront aussi traités';

$string['debug_ldap_groupes']='Mode verbose';
$string['debug_ldap_groupes_desc']='permet d\'avoir des scripts très bavards lors de la phase de test';

$string['cohort_synching_ldap_groups_autocreate_cohorts']='Créer les cohortes automatiquement';
$string['cohort_synching_ldap_groups_autocreate_cohorts_desc']='Si desactivé, seules les cohortes existantes avec un numéro d\'identification  identique aux noms des groupes LDAP seront synchronisées';

$string['cohort_synchronized_with_group']='Cohorte synchronisée avec le groupe LDAP {$a}';
$string['cohort_synchronized_with_attribute']='Cohorte synchronisée avec l\'attribut LDAP {$a}';

$string['synccohortattribute']='Synchro des cohortes Moodle selon les valeurs d\'un attribut LDAP';
$string['synccohortattribute_info']='Permet de synchroniser des cohortes Moodle selon les valeurs différentes d\'un attribut utilisateur déclaré dans un annuaire LDAP';

$string['cohort_synching_ldap_attribute_attribute']='Nom de l\'attribut ';
$string['cohort_synching_ldap_attribute_attribute_desc']='nom de l\'attribut à utiliser pour determiner les cohortes (respecter la casse) ';

$string['cohort_synching_ldap_attribute_idnumbers']='Target cohorts idnumbers';
$string['cohort_synching_ldap_attribute_idnumbers_desc']='une liste separée par des virgules des numéro d\'identification des cohortes à traiter.
 Si vide, chaque valeur différente de l\'attribut recherché produira une cohorte synchronisée';

$string['cohort_synching_ldap_attribute_objectclass']='User class';
$string['cohort_synching_ldap_attribute_objectclass_desc']='si defini, remplace la valeur de la classe des utilisateurs définie dans la configuration LDAP ou CAS ';

$string['cohort_synching_ldap_attribute_autocreate_cohorts']='Créer les cohortes automatiquement';
$string['cohort_synching_ldap_attribute_autocreate_cohorts_desc']='Si desactivé, seules les cohortes existantes avec un numéro d\'identification  identique aux valeurs de l\'attribut  LDAP seront synchronisées ';




?>
