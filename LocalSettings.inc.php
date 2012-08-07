<?php

// Set error reporting state: Recommend using E_ALL for development environment
	error_reporting(E_ALL);
	ini_set('display_errors',true);

// Set the root directory
	define ('MCL_ROOT', '/Users/paynejd/Sites/mcl/mcl-search/');

// DO NOT EDIT - Include the default settings
	require_once(MCL_ROOT . 'fw/DefaultSettings.inc.php');

// MCL Mode
	$mcl_mode = MCL_MODE_ENHANCED;

// Default database names. Should be modified in LocalSettings.inc.php if different.
	$mcl_default_concept_dict_db    =  'openmrs'                      ;
	$mcl_default_concept_dict_name  =  'CIEL/MVP Concept Dictionary'  ;
	$mcl_enhanced_db_name           =  'mcl'                          ;

// Database connection
	$mcl_db_host  =  'localhost'   ;
	$mcl_db_uid   =  'mcl_search'  ;
	$mcl_db_pwd   =  'mcl_pwd'     ;

?>
