<?php

require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'MclUser.inc.php');
session_start();

// Make sure not already signed in

?>
<html>
<head>
<title>Sign Up - MCL</title>
<link href="main.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="divToolbar" style="height:20px;">
	<div id="menu" style="float:left;width:200px;padding:2px;">
		<a href="/">Home</a>&nbsp;&nbsp;&nbsp;
		<a href="search.php">Search</a>&nbsp;&nbsp;&nbsp;
	</div>
</div>


<div id="content">
	<form action="signin.php" method="post">
	<div id="signin" class="shadow">
		<h2>MCL Sign Up for an Account</h2>
<?php
if (!is_null($result)) {
	echo '<div class="loginerr">Invalid email and password combination!</div>';
}
?>
		<table>
			<tr><td><label for="uid">Email</label></td>
				<td><input type="text" name="uid" id="uid" value="<?php echo $uid ?>" /></td></tr>
			<tr><td><label for="pwd">Password</label></td>
				<td><input type="password" name="pwd" id="pwd" /></td></tr>
			<tr><td></td>
				<td><input type="submit" value="Sign In" />
					<a style="float:right;" href="signup.html">Sign Up</a>
					</td></tr>
		</table>
	</div>
	</form>
</div>


</body>
</html>