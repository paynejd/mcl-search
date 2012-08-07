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
	$list_name = '';
	$list_description = '';
	if ($action == 'new') {
		$list_name = $_POST['name'];
		//$list_description = $_POST['desc'];	
	}

// debug stuff
	echo '<pre>',  var_dump($_POST), '</pre>';
	$i = 0;
	echo '<table border="1">';
	echo '<tr><th>#</th><th>dict_id</th><th>csrg_id</th><th>concept_id</th><th>name</th></tr>';
	foreach ($arr_concepts as $c) 
	{
		$i++;
		echo '<tr><td>' . $i . '</td>';
		echo '<td>' . $c -> dict_id . '</td>';
		echo '<td>' . $c -> csrg_id . '</td>';
		echo '<td>' . $c -> concept_id . '</td>';
		echo '<td>' . $c -> name . '</td>';
		echo '</tr>';
	}
	echo '</table>';

// open db connection
	$cxn = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd);
	if (!$cxn) {
		die('Could not connect to database: ' . mysql_error());
	}
	mysql_select_db($mcl_enhanced_db_name);


// New list
if ($_POST['action'] == 'new') 
{
	// Create the new list definition
	$cld = new ConceptListDefinition();
	$cld->setName($list_name);
	$cld->setDescription($list_description);

	// create the new list
	$cl = new ConceptList($cld);
	foreach ($arr_concepts as $c) {
		$cl->addConcept($c->concept_id);
		// TODO: $cl->addConcept($c->dict_id, $c->concept_id);
	}

	// Commit to database
	$clf = new ConceptListFactory();
	$clf->setConnection($cxn);
	if (!$clf->insertConceptList($cl)) {
		trigger_error('Could not create list', E_USER_ERROR);
	} else {
		echo 'successfully created list!';
	}
} 

// Add to list
elseif ($action == 'add')
{
	// 
}

// Remove from list
elseif ($action == 'remove')
{
	//
}

else {
	
}
