<?php


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
require_once(MCL_ROOT . 'fw/ConceptListFactory.inc.php');
require_once(MCL_ROOT . 'fw/ConceptSearchSourceFactory.inc.php');

session_start();

define(  'MCL_CONCEPT_LIST_MERGE_TYPE_INTERSECT'  ,  'intersect'  );
define(  'MCL_CONCEPT_LIST_MERGE_TYPE_UNION'      ,  'union'      );
define(  'MCL_CONCEPT_LIST_MERGE_MODE_DISPLAY'    ,  1            );
define(  'MCL_CONCEPT_LIST_MERGE_MODE_COMMIT'     ,  5            );


/****************************************************************************************
**  SETUP
****************************************************************************************/

// Get the user
	$user = null;
	if (MclUser::isLoggedIn()) {
		$user = MclUser::getLoggedInUser();
	}

// Set defaults
	$collection_ids  =  null                       ;		// No collections
	$debug           =  false                      ;		// Displays debug information
	$type            =  MCL_CONCEPT_LIST_MERGE_TYPE_UNION  ;
	$mode            =  MCL_CONCEPT_LIST_MERGE_MODE_DISPLAY;
	$new_list_name   =  '';

// open db connection
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_enhanced_db_name);

// Set Parameters
	if (  isset($_GET[ 'collection' ])  )  $collection_ids  =       $_GET[ 'collection' ]  ;
	if (  isset($_GET[ 'debug'      ])  )  $debug           = (bool)$_GET[ 'debug'      ]  ;
	if (  isset($_GET[ 'type'       ])  )  $type            =       $_GET[ 'type'       ]  ;
	if (  isset($_GET[ 'mode'       ])  )  $mode            =       $_GET[ 'mode'       ]  ;
	if (  isset($_GET[ 'name'       ])  )  $new_list_name   =       $_GET[ 'name'       ]  ;

// Create ConceptListFactory
	$clf = new ConceptListFactory();
	$clf->setConnection($cxn)  ;
	$clf->debug  =  $debug     ;

// Verify parameters
	$arr_collection_id  =  array();
	if (  $collection_ids  ) $arr_collection_id  =  explode(',',$collection_ids);
	if (  count($arr_collection_id) <= 1  )  {
		die('Parameter "collection" must contain 2 or more collection IDs separated by commas');
	}
	if (  $mode == MCL_CONCEPT_LIST_MERGE_MODE_COMMIT  )  
	{
		if (  !($new_list_name)  )  {
			trigger_error('Parameter "name" required if creating new list', E_USER_ERROR);
		} elseif (  !$clf->isUniqueConceptListName($new_list_name)  )  {
			trigger_error('List name "' . $new_list_name . '" already in use', E_USER_ERROR);
		}
	}

// Load all dictionary sources
	$cssf          =  new ConceptSearchSourceFactory();
	$cssf->debug   =  $debug;
	$coll_source   =  new ConceptSearchSourceCollection();
	$coll_source->add($cssf->loadDictionaryDefinitions($cxn));


/****************************************************************************************
**  Load Collections
****************************************************************************************/

	$arr_collection = array();
	foreach ($arr_collection_id as $collection_id) {
		$cl = $clf->loadConceptList($collection_id, MCL_CLTYPE_CONCEPT_LIST);
		if ($cl) {
			$arr_collection[$collection_id] = $cl;
		} else {
			echo '<p>Could not load list "' . $collection_id . '"</p>';
		}
	}
	$arr_collection_id = array_keys($arr_collection);

	if ($debug) echo "<pre>", var_dump($arr_collection), "</pre><hr />";


/****************************************************************************************
**  Intersect/Union
****************************************************************************************/

	$cl = null;
	if ($type == MCL_CONCEPT_LIST_MERGE_TYPE_INTERSECT) {
		$cl = ConceptListFactory::intersect($arr_collection);
	} elseif ($type == MCL_CONCEPT_LIST_MERGE_TYPE_UNION) {
		$cl = ConceptListFactory::union($arr_collection);
	} else {
		trigger_error('Unrecognized merge type: "' . $type . '"', E_USER_ERROR);
	}


/****************************************************************************************
**  Display/Commit
****************************************************************************************/

if ($mode == MCL_CONCEPT_LIST_MERGE_MODE_DISPLAY) 
{
	echo '<hr />';
	var_dump($cl);
} 
elseif ($mode == MCL_CONCEPT_LIST_MERGE_MODE_COMMIT) 
{
	// prepare the list definition
	$cld = new ConceptListDefinition();
	$cld->setName($new_list_name);
	$cl->cld = $cld;

	// save
	$new_concept_list_id = $clf->createConceptList($cl);

	var_dump($cl);
}

?>