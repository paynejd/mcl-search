<?php
/****************************************************************************************************
** user.php
**
** UNDER CONSTRUCTION
**
** View and edit user account settings.
** --------------------------------------------------------------------------------------------------
** POST Parameters:
*****************************************************************************************************/


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
session_start();
$user = MclUser::getLoggedInUser();

?>
<html>
<head>
<link href="main.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="divToolbar" style="height:20px;">
	<div id="menu" style="float:left;width:200px;padding:2px;">
		<a href="/">Home</a>&nbsp;&nbsp;&nbsp;
		<a href="search.php">Search</a>&nbsp;&nbsp;&nbsp;
	</div>
<?php if ($user) { ?>
	<div id="user" style="float:right;padding:2px;">
		<a href="user.php"><?php echo $user->uid; ?></a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="signout.php">Sign Out</a>
	</div>
<?php  } else {  ?>
	<div id="signin" style="float:right;">
		<form id="form_user" action="signin.php" method="post">
			<input type="text" name="uid" style="font-size:8pt;width:100px;color:#999;margin:0;" value="Email" />
			<input type="password" name="pwd" style="font-size:8pt;width:100px;color:#999;margin:0;" value="Password" />
			<input type="submit" value="Sign In" />&nbsp;&nbsp;|&nbsp;&nbsp;<a href="signup.html">Sign Up</a>
		</form>
	</div>
<?php } ?>
</div>
<div>
<?php
if ($user) {
	var_dump($user);
} else {
	echo 'You must be signed in to access this page!';
}
?>
</div>
</body>
</html>