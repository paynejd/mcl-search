<?php

require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'MclUser.inc.php');
session_start();

MclUser::signout();

// Build redirect url
	if (isset($_POST['r'])) {
		$url = $_POST['r'];
	} else {
		$url = 'search.php';
	}

// Redirect
	header('Location:' . $url);

?>