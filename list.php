<?php

require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'ConceptListFactory.inc.php');
require_once(MCL_ROOT . 'ConceptListDefinition.inc.php');

// Get the parameters
	$arr_concepts = null;
	if (isset($_POST['concepts'])) {
		$arr_concepts = json_decode($_POST['concepts']);
	}
	$action = $_POST['action'];

// debug stuff
/*
	echo '<pre>',  var_dump($_POST), '</pre>';
	$i = 0;
	echo '<table border="1">';
	echo '<tr><th>#</th><th>dict_db</th><th>csrg_id</th><th>concept_id</th><th>name</th></tr>';
	foreach ($arr_concepts as $c) 
	{
		$i++;
		echo '<tr><td>' . $i . '</td>';
		echo '<td>' . $c -> dict_db . '</td>';
		echo '<td>' . $c -> csrg_id . '</td>';
		echo '<td>' . $c -> concept_id . '</td>';
		echo '<td>' . $c -> name . '</td>';
		echo '</tr>';
	}
	echo '</table>';
 */

// open db connection
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_enhanced_db_name);
	$clf = new ConceptListFactory();
	$clf->setConnection($cxn);


// New list
if ($_POST['action'] == 'new') 
{
	$list_name  =  $_POST['name'];
	$list_desc  =  $_POST['desc'];

	// Create the new list definition
	$cld = new ConceptListDefinition();
	$cld->setName($list_name);
	$cld->setDescription($list_desc);

	// create the new list
	$cl = new ConceptList($cld);
	foreach ($arr_concepts as $c) {
		$cl->addConcept($c->dict_db, $c->concept_id);
	}

	// Commit to database
	if (!$clf->createConceptList($cl)) {
		trigger_error('Could not create list', E_USER_ERROR);
	} else {
		echo 'Successfully created list!';
	}
}

// Add to list
elseif ($action == 'add')
{
	// Extract the list ID
	$matches = array();
	if (!preg_match('~list\((\d+)\)~', $_POST['list'], $matches)) {
		echo 'Select a list!';
		exit();
	}
	$list_id = $matches[1];
	$cld = new ConceptListDefinition();
	$cld->setListId($list_id);

	// Create the list with concepts to add
	$cl = new ConceptList(null);
	foreach ($arr_concepts as $c) {
		$cl->addConcept($c->dict_db, $c->concept_id);
	}	

	// Update the list
	if ($clf->addConceptsToList($cld, $cl)) {
		echo 'Concepts added to list!';
	} else {
		echo 'An error occurred adding concepts to list.';
	}
}

// Remove from list
elseif ($action == 'remove')
{
	// Extract the list ID
	$matches = array();
	if (!preg_match('~list\((\d+)\)~', $_POST['list'], $matches)) {
		echo 'Select a list!';
		exit();
	}
	$list_id = $matches[1];
	$cld = new ConceptListDefinition();
	$cld->setListId($list_id);

	// Create the list with concepts to add
	$cl = new ConceptList(null);
	foreach ($arr_concepts as $c) {
		$cl->addConcept($c->dict_db, $c->concept_id);
	}

	// Update the list
	if ($clf->removeConceptsFromList($cld, $cl)) {
		echo 'Concepts removed from list!';
	} else {
		echo 'An error occurred removing concepts from list.';
	}
}

else {
	
}
