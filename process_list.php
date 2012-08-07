<?php
/****************************************************************************************************
** process_list.php
**
** This script inserts, updates, and deletes Concept Lists to the MCL concept_list table.
** Note that this functionality is only supported when running in ENHANCED MODE.
** --------------------------------------------------------------------------------------------------
** GET Parameters:
**     s (required) - case insensitive type of submission: update, create new, delete
**     name - must be unique
**     source - db name of the concept dictionary source
**     list - the concept_list_id (ignored if submit type is 'create new')
**     concepts - comma-separated list of 
**     admin - set to 1 to allow editing of restricted lists
*****************************************************************************************************/


// Set error reporting state
	ini_set('display_errors',1);
	error_reporting(E_ALL|E_STRICT);

// Start the session before including files
	session_start();

// Include dependencies
	require_once('LocalSettings.inc.php');
	require_once('fw/ConceptListFactory.inc.php');
	require_once('fw/search_common.inc.php');

// Set debug status
	$debug = false;
	if (isset($_POST['debug']) && $_POST['debug']) {
		$debug = true;
		echo '<pre>', var_dump($_POST), '</pre>';
	}

// Make sure the form submit post parameter was passed
	if (!isset($_POST['s']) || !$_POST['s']) {
		trigger_error('<strong>s</strong> is a required form post parameter.', E_USER_ERROR);
	}

// Determine submission type
	$submit = strtolower($_POST['s']);
	if ($submit != 'update' && $submit != 'create new' && $submit != 'delete') {
		trigger_error("Allowed values for <strong>s</strong> are 'update', " . 
			"'create new', and 'delete'.", E_USER_ERROR);
	}

// Get the form variables
	$name = $_POST['name'];
	$source = $_POST['source'];
	$list_id = null;
	if (isset($_POST['list'])) $list_id = $_POST['list'];
	$concepts = null;
	if (isset($_POST['concepts'])) $concepts = $_POST['concepts'];

// Connect to db
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_default_concept_dict_db, $cxn);
	$clf  =  new ConceptListFactory();
	$clf->setConnection($cxn);
	$clf->debug = $debug;

// Determine if this is a restricted list
// NOTE: right now, this only applies to the MCL Core. Can override with 'admin' parameter.
	$restrict_editing = false;
	if ($list_id == 1) {	
		if (!isset($_POST['admin'])) $restrict_editing = true;
	}


/****************************************************************************
**	Process Delete
****************************************************************************/

if ($submit == 'delete')
{
	// Make the sure the right parameters are set
	if (!$list_id) {
		trigger_error('<strong>list</strong> is a required form post parameter ' .
			"for submit type 'delete'.", E_USER_ERROR);
	}

	// Throw error if deleting one of the restricted lists
	if ($restrict_editing) {
		trigger_error("Cannot delete restricted lists without admin privileges", E_USER_ERROR);
	}
	
	// Delete the list
	if (!$clf->deleteConceptList($list_id)) {
		trigger_error("Cannot delete concept_list_id: " . $list_id, E_USER_ERROR);
	}

	// Set the redirect url
	$url = 'list.php';
}


/****************************************************************************
**	Process Delete
****************************************************************************/

elseif ($submit == 'create new')
{
	// Make sure the right parameters are set
	// NOTE: 'concepts' is an optional parameter
	if (!$name || !$source) {
		trigger_error('<strong>name</strong> and <strong>source</strong> are ' . 
			"required form post parameters for submit type 'create new'.", E_USER_ERROR);
	}

	// Add the list
	$new_concept_list_id = $clf->addConceptList($name, $source, $concepts);

	// Set the redirect url
	if ($new_concept_list_id) {
		$url = 'list.php?list=' . $new_concept_list_id;
	} else {
		// todo: need to pass error message to user
		$url = 'list.php';
	}	
}


/****************************************************************************
**	Update Concept List (for updates or new lists)
****************************************************************************/

elseif ($submit == 'update')
{
	// Make sure the right parameters are set
	if (!$list_id) {
		trigger_error('<strong>list</strong> is a required form post parameter ' .
			"for submit type 'update'.", E_USER_ERROR);
	}

	// Throw error if updating one of the restricted lists
	if ($restrict_editing) {
		trigger_error("Cannot update restricted lists without administrative privileges", E_USER_ERROR);
	}

	// Update the list
	if (!$clf->updateConceptList($list_id, $name, $source, $concepts)) {
		trigger_error('Unable to update concept list: ' . $list_id, E_USER_ERROR);
	}
	
	// Set the redirect url
	$url = 'list.php?list=' . $list_id;
}


/****************************************************************************
**	Redirect back to list.php
****************************************************************************/

// Get back to list.php
	if ($debug) {
		echo '<p>Redirect to: <a href="' . $url . '">' . $url . '</a></p>';
	} else {
		header('Location:' . $url);
	}
	exit();

?>