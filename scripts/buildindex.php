<?php
/****************************************************************************************************
** rebuild_index.php
**
** Rebuilds the table in the MCL database that serves as the basis for Solr indexing.
** --------------------------------------------------------------------------------------------------
** get/post parameters:
**		source				(Not Implemented) comma-separated list of concept sources to rebuild. 
**								format: <dictionary_name>[:map(<map_source_id>)]
*****************************************************************************************************/


set_time_limit(0);
error_reporting(-1);
ini_set('display_errors',1);

require_once('../LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchSourceFactory.inc.php');
require_once(MCL_ROOT . 'fw/MclIndex.inc.php');

echo '<pre>';

// Grab the GET/POST page parameters
	$arr_param  =  array_merge(  $_POST  ,  $_GET  );

// Make sure in enhance mode
	if (  $mcl_mode  !=  MCL_MODE_ENHANCED  ) 
	{
		trigger_error('Enhanced mode must be enabled to run buildindex.php', E_USER_ERROR);
		exit();
	}

// Connect to MCL db
	if (  !($cxn_mcl = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd))  ) {
		die('Could not connect to database: ' . mysql_error($cxn_mcl));
	}
	mysql_select_db($mcl_enhanced_db_name, $cxn_mcl);

// Create the index table
	$result  =  MclIndex::buildIndex(  $cxn_mcl  ,  $mcl_enhanced_db_name,  
			$mcl_base_index_table_name  ,  $mcl_mapsource_index_table_name  );


?>