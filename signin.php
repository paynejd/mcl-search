<?php
/****************************************************************************************************
** signin.php
**
** Page to authenticate a user. Redirects upon success; displays a login form 
** upon failure or if no credentials are passed. Credentials must be passed through POST.
** --------------------------------------------------------------------------------------------------
** POST parameters:
**		uid 	Username
**		pwd		Password
**		r		Redirect url
*****************************************************************************************************/


require_once('LocalSettings.inc.php');
require_once(MCL_ROOT . 'fw/MclUser.inc.php');
session_start();


$do_signin = false;


// Clear out any currently signed in user just in case
	// TODO: Consider just telling them that they are already signed in rather than kicking them out
	MclUser::signout();

// Make sure valid username and password were submitted
	$uid     =  '';
	if (isset($_POST['uid']) && isset($_POST['pwd'])) {
		$do_signin = true;
		$uid = $_POST['uid'];
	} elseif (isset($_POST['uid'])) {
		$uid = $_POST['uid'];
	}

// Sign-in
	$result  =  null;
	$user    =  null;
	if ($do_signin) 
	{
		// Validate entries
			if (!MclUser::isValidUsername($uid) ||
				!MclUser::isValidPassword($_POST['pwd'])  )
			{
				$result = MCL_AUTH_INVALID_FORMAT;
			}

		// Connect to MCL db
			if (  $result != MCL_AUTH_INVALID_FORMAT  )  
			{
				if (  !($cxn_mcl = mysql_connect($mcl_db_host, $mcl_db_uid, $mcl_db_pwd))  )  {
					die('Could not connect to database: ' . mysql_error($cxn_mcl));
			 	}
				mysql_select_db($mcl_enhanced_db_name);
			}

		// Authenticate
			if (  $result != MCL_AUTH_INVALID_FORMAT  )  
			{
				$user = MclUser::authenticate($cxn_mcl, $uid, $_POST['pwd']);
				if ($user) {
					// Successful authentication, so sign in user
					MclUser::signin($user);
					$result = MCL_AUTH_SUCCESS;
				} else {
					// Invalid username and password combination
					$result = MCL_AUTH_INVALID_UID_OR_PWD;
				}
			}
	}

// Redirect on success
	if (isset($_POST['r'])) $url = $_POST['r'];
	else $url = 'search.php';
	if ($result == MCL_AUTH_SUCCESS) {
		header('Location:' . $url);
		exit();
	}

?>
<html>
<head>
<title>Sign In - MCL</title>
<link href="main.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/jquery-1.6.4.js"></script>
<script type="text/javascript" src="js/jquery.watermark.js"></script>
<script type="text/javascript">
$(document).ready(function() {
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
	<form action="signin.php" method="post">
	<div id="signin" class="shadow">
		<h2>MCL:Search Sign In</h2>
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
					<a style="float:right;" href="signup.php">Sign Up</a>
					</td></tr>
		</table>
	</div>
	</form>
</div>

</body>
</html>