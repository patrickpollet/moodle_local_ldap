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
 *
 * @package local
 * @subpackage ldap
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @copyright 2013 onwards Patrick Pollet {@link mailto:pp@patrickpollet.net}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
//    require_once(dirname(__FILE__).'/lib.php');
	    
    $settings = new admin_settingpage('local_ldap', get_string('pluginname', 'local_ldap'));
  
    
    $name = 'debug_ldap_groupes';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configcheckbox('local_ldap/'.$name, $title, $description, false);
    $settings ->add($setting);  
    
    
    $settings->add(new admin_setting_heading('synccohortgroup', 
                    get_string('synccohortgroup', 'local_ldap'),
                    get_string('synccohortgroup_info', 'local_ldap')));
                    

    $name = 'group_attribute';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configtext('local_ldap/'.$name, $title, $description, 'cn');
    $settings ->add($setting);   
                    
    $name = 'group_class';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configtext('local_ldap/'.$name, $title, $description, 'groupOfUniqueNames');
    $settings ->add($setting);   
   
    $name = 'real_user_attribute';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configtext('local_ldap/'.$name, $title, $description,'');
    $settings ->add($setting);   
   
                    
    $name = 'process_nested_groups';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configcheckbox('local_ldap/'.$name, $title, $description, false);
    $settings ->add($setting);   

 
    
    $name = 'cohort_synching_ldap_groups_autocreate_cohorts';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configcheckbox('local_ldap/'.$name, $title, $description, false);
    $settings ->add($setting);
                    

    
    
    $settings->add(new admin_setting_heading('synccohortattribute', 
                    get_string('synccohortattribute', 'local_ldap'),
                    get_string('synccohortattribute_info', 'local_ldap')));
                    

    $name = 'cohort_synching_ldap_attribute_attribute';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configtext('local_ldap/'.$name, $title, $description, 'eduPersonAffiliation');
    $settings ->add($setting);                 

    $name = 'cohort_synching_ldap_attribute_idnumbers';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configtext('local_ldap/'.$name, $title, $description, '');
    $settings ->add($setting);   
    
    $name = 'cohort_synching_ldap_attribute_objectclass';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configtext('local_ldap/'.$name, $title, $description, '');
    $settings ->add($setting);   
                    
    
    $name = 'cohort_synching_ldap_attribute_autocreate_cohorts';
    $title = get_string($name,'local_ldap');
    $description = get_string($name.'_desc','local_ldap');  
    $setting = new admin_setting_configcheckbox('local_ldap/'.$name, $title, $description, false);
    $settings ->add($setting);                
                    
        
    $ADMIN->add('localplugins', $settings);    
    
}

