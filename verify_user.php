<?php
/****************************************************************************************************
** verify_user.php
**
** Page to verify a user email account.
** --------------------------------------------------------------------------------------------------
** TO DO:
**  -  
** --------------------------------------------------------------------------------------------------
** GET Parameters:
** 		u* 		username
**		h* 		hash code
*****************************************************************************************************/


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);


// Validate user and hash code
	if (  !isset($_GET['u'])  ||  !isset($_GET['h'])  ||  
			empty($_GET['u'])  ||  empty($_GET['h'])  ) 
	{
		exit('Missing required parameters');
	}
	if (  !MclUser::isValidEmail($_GET['u'])  ) {
		exit('invalid user email account');
	}
	$uid   =  $_GET['u'];
	$hash  =  $_GET['h'];

// Sign out current user - in case they got here on accident
	MclUser::signout();

// Connect to MCL db
	if (!($cxn_mcl = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd))) {
		die('Could not connect to database: ' . mysql_error($cxn_mcl));
 	}
 	mysql_select_db($mcl_enhanced_db_name, $cxn_mcl);

// Verify User
	if (  $is_verified = MclUser::verifyUser($cxn_mcl, $uid, $hash)  )
	{
		// TODO: User is verified
	} else {
		// TODO: Unable to verify user
	}

?>
<html>
<head>
<title>Verify User - MCL</title>
<link href="main.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery-1.6.4.js"></script>
<script type="text/javascript" src="js/jquery.watermark.js"></script>
<script type="text/javascript">
$(document).ready(function() {
	$('#uid').watermark('Email'   );
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
	<div id="signin_mini" style="float:right;">
		<form id="form_user" action="signin.php" method="post">
			<input type="text" id="uid" name="uid" />
			<input type="password" id="pwd" name="pwd" />
			<input type="submit" id="btnsignin" value="Sign In" />&nbsp;&nbsp;|&nbsp;&nbsp;<a href="signup.php">Sign Up</a>
		</form>
	</div>
</div>

<div id="content">

<?php  if (  $is_verified  )  {  ?>

	<div id="signin" class="shadow">
		<h2>Your account is activated!</h2>
		<p style="font-size:12pt;">Your email address, <span style="color:blue;font-weight:bold;"><?php echo $uid ?></span>,  
			is now activated.</p> 
		<p style="font-size:12pt;">You may now <a href="signin.php">Sign In</a> to start using the full functionality
			of <strong>MCL:Search</strong>.</p>
	</div>

<?php  }  else  {  ?>

	<div id="signin" class="shadow">
		<h2>Oops...</h2>
		<p style="font-size:12pt;">We could not verify your email address, 
			<span style="color:blue;font-weight:bold;"><?php echo $uid ?></span>.  
			Please try again.</p>
		<p style="font-size:12pt;">If the problem persists, please contact 
			<a href="mailto:info@maternalconceptlab.org">info@maternalconceptlab.org</a>.</p>
	</div>

<?php  }  ?>

</div>


</body>
</html>