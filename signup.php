<?php
/****************************************************************************************************
** signup.php
**
** UNDER CONSTRUCTION
**
** Page request a new user account.
** --------------------------------------------------------------------------------------------------
** POST Parameters:
*****************************************************************************************************/


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
session_start();

// Make sure not already signed in
$result = null;
$fname = '';
$lname = '';
$org = '';
$uid = '';
$pwd = '';

?>
<html>
<head>
<title>Sign Up - MCL</title>
<link href="main.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery-1.6.4.js"></script>
<script type="text/javascript" src="js/jquery.watermark.js"></script>
<script type="text/javascript">
$(document).ready(function() {
	$('#fname').watermark('First Name');
	$('#lname').watermark('Last Name');
	$('#org').watermark('Organization');
	$('#uid').watermark('Email');
	$('#pwd').watermark('Password');
});
</script>
</head>
<body>
<div id="divToolbar" style="height:20px;">
	<div id="menu" style="float:left;width:200px;padding:2px;">
		<a href="/">Home</a>&nbsp;&nbsp;&nbsp;
		<a href="search.php">Search</a>&nbsp;&nbsp;&nbsp;
	</div>
</div>


<div id="content">
	<form action="signup.php" method="post">
	<div id="signin" class="shadow">
		<h2>MCL:Search Sign Up</h2>
<?php
if (!is_null($result)) {
	echo '<div class="loginerr">Invalid email and password combination!</div>';
}
?>
		<table>
			<tr><td><label for="uid">First:</label></td>
				<td><input type="text" name="fname" id="fname" value="<?php echo $fname ?>" /></td></tr>
			<tr><td><label for="uid">Last:</label></td>
				<td><input type="text" name="lname" id="lname" value="<?php echo $lname ?>" /></td></tr>
			<tr><td><label for="uid">Org:</label></td>
				<td><input type="text" name="org" id="org" value="<?php echo $org ?>" /></td></tr>
			<tr><td><label for="uid">Email:</label></td>
				<td><input type="text" name="uid" id="uid" value="<?php echo $uid ?>" /></td></tr>
			<tr><td><label for="pwd">Password:</label></td>
				<td><input type="password" name="pwd" id="pwd" /></td></tr>
			<tr><td></td>
				<td><input type="submit" value="Submit" /></td></tr>
		</table>
	</div>
	</form>
</div>


</body>
</html>