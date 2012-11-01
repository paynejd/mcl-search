<?php
/****************************************************************************************************
** signup.php
**
** UNDER CONSTRUCTION
**
** Page request a new user account.
** --------------------------------------------------------------------------------------------------
** TO DO:
**  -  Inline Javascript form validation
** --------------------------------------------------------------------------------------------------
** POST Parameters:
** 		fname
**		lname
**		org
**		uid
**		pwd
**		confirm
*****************************************************************************************************/


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);


// Sign out current user - in case they got here on accident
	MclUser::signout();

// Initialize
	$result    =  null  ;		// Outcome of new user creation
	$user      =  null  ;
	$cxn_mcl   =  null  ;
	$fname     =  (isset($_POST['fname'  ])) ? $_POST['fname'  ] : ''  ;
	$lname     =  (isset($_POST['lname'  ])) ? $_POST['lname'  ] : ''  ;
	$org       =  (isset($_POST['org'    ])) ? $_POST['org'    ] : ''  ;
	$uid       =  (isset($_POST['uid'    ])) ? $_POST['uid'    ] : ''  ;
	$pwd       =  (isset($_POST['pwd'    ])) ? $_POST['pwd'    ] : ''  ;
	$confirm   =  (isset($_POST['confirm'])) ? $_POST['confirm'] : ''  ;
	$is_error  =  false;		// did a form validation error occur?
	$arr_err   =  array();		// stores the validation error messages
	$is_form_submit  =  false;	// did a form submission occur?

// Create and validate user object if form submission occurred
	if (  $_SERVER['REQUEST_METHOD'] == 'POST'  )  
	{
		$is_form_submit  =  true;
		$user  =  new MclUser(null, $uid, $fname, $lname, $org, false);
		if (  !MclUser::isValid($user, $arr_err)  )  
		{
			$is_error  =  true;
		}  
		elseif (  $pwd  !==  $confirm  )  
		{
			$is_error  =  true;
			$arr_err['pwd']  =  'Passwords do not match!';
		}
		elseif (  !MclUser::isValidPassword($pwd, $arr_err)  )
		{
			$is_error  =  true;
		}
	}

// Connect to database and ensure that email adress is unique
	if (  $is_form_submit  &&  !$is_error  )
	{
		// Connect to MCL db
		if (!($cxn_mcl = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd))) {
			die('Could not connect to database: ' . mysql_error($cxn_mcl));
	 	}
	 	mysql_select_db($mcl_enhanced_db_name, $cxn_mcl);

		// Check that username is unique
		if (MclUser::doesUserExist($cxn_mcl, $uid))  {
			$arr_err['uid'] = 'This email address is already in use!';
			$is_error = true;
		}
	}

// Create the new user as an unverified user
// NOTE: Unverified users cannot do anything that is visible publicly
	if (  $is_form_submit  &&  !$is_error  )
	{
		if (  $result  =  MclUser::createUser($cxn_mcl, $user, $pwd)  )  
		{
			// Send user verification email
			if (  !(  $is_email_sent = MclUser::sendVerificationEmail($cxn_mcl, $user)  )  )  
			{
				// Could not send email, delete user record and prompt user to register again
				MclUser::deleteUser($cxn_mcl, $user);
			}
		}
	}


?>
<html>
<head>
<title>Sign Up - MCL</title>
<link href="main.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery-1.6.4.js"></script>
<script type="text/javascript" src="js/jquery.watermark.js"></script>
<script type="text/javascript">
$(document).ready(function() {
	$('#fname'  ).watermark('First Name'      );
	$('#lname'  ).watermark('Last Name'       );
	$('#org'    ).watermark('Organization'    );
	$('#uid'    ).watermark('Email'           );
	$('#pwd'    ).watermark('Password'        );
	$('#confirm').watermark('Confirm Password');
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

<?php  if (  $is_form_submit  &&  $result  &&  $is_email_sent  )  {  ?>

	<div id="signin" class="shadow">
		<h2>One more step...</h2>
		<p style="font-size:12pt;">An email has been sent to <span style="color:#666;font-weight:bold;"><?php echo $uid ?></span>.  
			Follow the instructions in the email to complete your registration.</p>
	</div>

<?php  }  elseif (  $is_form_submit  &&  $result  &&  !$is_email_sent  )  {  ?>

	<div id="signin" class="shadow">
		<h2>Oops...</h2>
		<p style="font-size:12pt;">Unable to send email to <span style="color:blue;font-weight:bold;"><?php echo $uid ?></span>.  
			Please try registering again.</p>
		<p style="font-size:12pt;"><a href="signup.php">Sign Up</a></p>
		<p style="font-size:12pt;">If the problem persists, please contact <a href="mailto:info@maternalconceptlab.org">info@maternalconceptlab.org</a>.</p>
	</div>

<?php  }  else  {  ?>

	<form action="signup.php" method="post">
	<div id="signin" class="shadow">
		<h2>MCL:Search Sign Up</h2>
		<table>
			<tr><td><label for="uid">First:</label></td>
				<td><?php if (isset($arr_err['fname'])) { ?>
					<span class="loginerr"><?php echo $arr_err['fname']; ?></span><br />
					<?php } ?>
					<input type="text" name="fname" id="fname" value="<?php echo $fname ?>" /></td></tr>
			<tr><td><label for="uid">Last:</label></td>
				<td><?php if (isset($arr_err['lname'])) { ?>
					<span class="loginerr"><?php echo $arr_err['lname']; ?></span><br />
					<?php } ?>
					<input type="text" name="lname" id="lname" value="<?php echo $lname ?>" /></td></tr>
			<tr><td><label for="uid">Org:</label></td>
				<td><?php if (isset($arr_err['org'])) { ?>
					<span class="loginerr"><?php echo $arr_err['org']; ?></span><br />
					<?php } ?>
					<input type="text" name="org" id="org" value="<?php echo $org ?>" /></td></tr>
			<tr><td><label for="uid">Email:</label></td>
				<td><?php if (isset($arr_err['uid'])) { ?>
					<span class="loginerr"><?php echo $arr_err['uid']; ?></span><br />
					<?php } ?>
					<input type="text" name="uid" id="uid" value="<?php echo $uid ?>" /></td></tr>
			<tr><td><label for="pwd">Password:</label></td>
				<td><?php if (isset($arr_err['pwd'])) { ?>
					<span class="loginerr"><?php echo $arr_err['pwd']; ?></span><br />
					<?php } ?>
					<input type="password" name="pwd" id="pwd" /></td></tr>
			<tr><td><label for="confirm">Confirm:</label></td>
				<td><?php if (isset($arr_err['confirm'])) { ?>
					<span class="loginerr"><?php echo $arr_err['confirm']; ?></span><br />
					<?php } ?>
					<input type="password" name="confirm" id="confirm" /></td></tr>
			<tr><td></td>
				<td><input type="submit" value="Submit" /></td></tr>
		</table>
	</div>
	</form>

<?php  }  ?>

</div>


</body>
</html>