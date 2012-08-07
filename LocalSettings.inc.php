<?php

error_reporting(E_ALL);
ini_set('display_errors',true);

define ('MCL_ROOT', '/var/www/html/mcl/');

require_once(MCL_ROOT . 'DefaultSettings.inc.php');



/**
 * MCL Mode
 */
	$mcl_mode = MCL_MODE_ENHANCED;

/**
 * Default database names. Should be modified in LocalSettings.inc.php if different.
 */
	$mcl_default_concept_dict_db    =  'openmrs'                      ;
	$mcl_default_concept_dict_name  =  'CIEL/MVP Concept Dictionary'  ;
	$mcl_enhanced_db_name           =  'mcl'                          ;

/** 
 * Database connection
 */
	$mcl_db_host  =  'localhost'  ;
	$mcl_db_uid   =  'username'   ;
	$mcl_db_pwd   =  'password'   ;


?>
