<?php
/****************************************************************************************************
** signout.php
**
** Page to signout a user. Redirect when complete.
** --------------------------------------------------------------------------------------------------
** POST parameters:
**		r		Redirect url
*****************************************************************************************************/


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
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