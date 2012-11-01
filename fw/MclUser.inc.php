<?php

/**
 * MCL User Object
 * Note that this object requires that the session be started to work
 */
class MclUser 
{
	public $user_id   =  null  ;
	public $uid       =  null  ;
	public $fname     =  null  ;
	public $lname     =  null  ;
	public $org       =  null  ;
	public $super     =  null  ;
	public $verified  =  null  ;

	public function __construct($user_id, $uid, $fname, $lname, $org, $super, $verified = null) 
	{
		$this->user_id   =  $user_id   ;
		$this->uid       =  $uid       ;
		$this->fname     =  $fname     ;
		$this->lname     =  $lname     ;
		$this->org       =  $org       ;
		$this->super     =  $super     ;
		$this->verified  =  $verified  ;
	}

	/**
	 * STATIC FUNCTIONS
	 */
	public function isLoggedIn() 
	{
		if (isset($_SESSION['__mcl_user__'])) return true;
		return false;
	}
	public function getLoggedInUser() 
	{
		if (MclUser::isLoggedIn()) {
			return $_SESSION['__mcl_user__'];
		}
		return null;
	}
	public function authenticate($cxn, $uid, $pwd) 
	{
		// TODO: Remove hard coded reference to 'mcl' database
		$uid = strtolower($uid);
		$sql = 
				"select user_id, email, fname, lname, org, super, verified " . 
				"from mcl.user " . 
				"where LOWER(email) = '" . 
				mysql_real_escape_string($uid, $cxn) . 
				"' and pwd = password('" . 
				mysql_real_escape_string($pwd, $cxn) . "')";
		if (  !($result = mysql_query($sql, $cxn))  ) {
			trigger_error('Could not authenticate user: ' . mysql_error($cxn), E_USER_ERROR);
			exit();
		}
		if (mysql_num_rows($result) >= 1) {
			$row = mysql_fetch_assoc($result);
			$user = new MclUser(
					$row[  'user_id'  ],
					$row[  'email'    ],
					$row[  'fname'    ],
					$row[  'lname'    ],
					$row[  'org'      ],
					$row[  'super'    ],
					$row[  'verified' ]
				);
			return $user;
		}
		return false;
	}
	public function doesUserExist($cxn, $uid) 
	{
		// TODO: Remove hard coded reference to 'mcl' database
		$uid = strtolower($uid);
		$sql = 
				"select count(*) as c from mcl.user where LOWER(email) = '" .
				mysql_real_escape_string($uid, $cxn) . "'";
		if (  !($result = mysql_query($sql, $cxn))  ) {
			var_dump($sql);
			trigger_error('Could query database: ' . mysql_error($cxn), E_USER_ERROR);
			exit();
		}
		$row  =  mysql_fetch_assoc($result);
		if (  $row['c']  )  return true;
		return false;
	}
	public function signin(MclUser $user) {
		$_SESSION['__mcl_user__'] = $user;
		return true;
	}
	public function signout() {
		if (isset($_SESSION['__mcl_user__']))  unset($_SESSION['__mcl_user__']);
		return true;
	}
	public function isValidUsername($uid) {
		return MclUser::isValidEmail($uid);
	}
	public function isValidPassword($pwd, array &$arr_err = null) {
		if (  strlen($pwd) < 6  ) {
			$arr_err['pwd']  =  'Password must contain at least 6 characters';
			return false;
		}
		return true;
	}


	/**
	 * Create new unverified user in the database
	 */
	public function createUser($cxn, MclUser $user, $pwd)
	{
		// TODO: Remove hard coded reference to mcl database
		$sql =
			'insert into mcl.user (email, pwd, fname, lname, org, super) values (' .
				"'" . mysql_real_escape_string(strtolower($user->uid), $cxn) . "', " .
				"password('" . mysql_real_escape_string($pwd, $cxn) . "'), " .
				"'" . mysql_real_escape_string($user->fname, $cxn) . "', " .
				"'" . mysql_real_escape_string($user->lname, $cxn) . "', " .
				"'" . mysql_real_escape_string($user->org  , $cxn) . "', " .
				($user->super ? '1' : '0') .
			')';
		if (  !($result = mysql_query($sql, $cxn))  ) {
			var_dump($sql);
			trigger_error('Could not create user: ' . mysql_error($cxn), E_USER_ERROR);
			exit();
		}
		return true;
	}


	/**
	 * Validates the user object. Returns true if fields are syntactically valid, 
	 * or false on error. Optionally pass an array by reference to get the error messages.
	 * Note that passwords are not validated since they are not stored in the user
	 * object. Note that the this function does not check if the username is unique.
	 */
	public static function isValid(MclUser $user, array &$arr_err = null)
	{
		if (  !$arr_err  )  $arr_err  =  array();

		// First name
		if (  !$user->fname  )  $arr_err['fname'] = 'First name is required!';

		// Last name
		if (  !$user->lname  )  $arr_err['lname'] = 'Last name is required!';

		// Org
		if (  !$user->org    )  $arr_err['org']   = 'Organization is required!';

		// Username / Email
		if (  !$user->uid    )  $arr_err['uid'] = 'Email address is required!';
		elseif (!MclUser::isValidUsername($user->uid))  $arr_err['uid'] = 'A valid email address is required!';

		if ($arr_err) return false;
		return true;
	}

	/**
	 * Deletes the user from the MCL database (both user and user_validation tables). Accepts
	 * either a valid user identifier (email address) or a MclUser object.
	 * Returns true on success or false if an error occurred.
	 */
	public static function deleteUser($cxn_mcl, $user)
	{
		if ($user instanceof MclUser) {
			$uid = $user->uid;
		} else {
			$uid = $user;
		}
		if (  !MclUser::isValidEmail($uid)  )  return false;

		// Delete old verification record if one exists
		$sql  =  "delete from user_validation where uid = '" . mysql_real_escape_string($uid, $cxn_mcl) . "'";
		if (  !mysql_query($sql, $cxn_mcl)  ) 
		{
			trigger_error('Could not execute query: ' . $sql . '; ' . mysql_error($cxn_mcl), E_USER_ERROR);
			exit();
		}

		// Delete user if exists
		$sql  =  "delete from user where email = '" . mysql_real_escape_string($uid, $cxn_mcl) . "'";
		if (  !mysql_query($sql, $cxn_mcl)  ) 
		{
			trigger_error('Could not execute query: ' . $sql . '; ' . mysql_error($cxn_mcl), E_USER_ERROR);
			exit();
		}

		return true;
	}

	/**
	 * Sends a verification email to the user which includes a link that must be clicked 
	 * on before the user account can be used.
	 */
	public static function sendVerificationEmail($cxn_mcl, MclUser $user)
	{
		// Create hash code
		$hash        =  md5(  $user->uid . rand(0,1000)  );
		$url_verify  =  
				'http://www.openconceptlab.org/mcl-search/verify_user.php?u=' . 
				urlencode($user->uid) . '&h=' . urlencode($hash);

		// Set email subject
		$subject  =  'MCL:Search Email Verification';

		// Set email headers
		$headers  =  "From: info@mopenconceptlab.org\r\n";
		$headers .=  "Reply-To: info@openconceptlab.org\r\n";
		$headers .=  "MIME-Version: 1.0\r\n";
		$headers .=  "Content-Type: text/html; charset=ISO-8859-1\r\n";

		// Set email body
		// TODO: Will need to change image url after migration
		$msg      =  
			'<html><body>' .
			'<div style="margin-left:auto;margin-right:auto;width:360px;border:1px solid #aaf;background:#fff;padding:25px;">' .
				'<img src="http://www.openconceptlab.org/mcl-search/images/mcl-search-logo.png" alt="MCL:Search Logo" />' .
				'<div style="font-weight:bold;font-size:16pt;font-family:Verdana;padding-bottom:20px;">Thank you for signing up!</div>' .
				'<div style="font-weight:normal;font-size:12pt;font-family:Verdana;padding-bottom:20px;">Click on the link below to verify your ' . 
				'email address and start using the full functionality of <strong>MCL:Search</strong>:</div>' .
				'<div style="font-weight:normal;font-size:12pt;font-family:Verdana;padding-bottom:20px;">' . 
				'<a href="' . htmlentities($url_verify) . '">' . htmlentities($url_verify) . '</a></div>' .
				'<div style="font-weight:normal;font-size:12pt;font-family:Verdana;padding-bottom:20px;">Look forward to seeing you ' .
				'soon at <a href="http://www.openconceptlab.org/">www.openconceptlab.org</a>.</div>' .
				'<div style="font-weight:normal;font-size:12pt;font-family:Verdana;padding-bottom:20px;">Thanks,<br />The MCL Team</div>' .
			'</div>' .
			'</body></html>';

		// Send email
		if (  !mail(  $user->uid  ,  $subject  ,  $msg  ,  $headers  )  )
		{
			return false;
		}

		// Delete old verification record if one exists
		$sql  =  "delete from user_validation where uid = '" . mysql_real_escape_string($user->uid, $cxn_mcl) . "'";
		if (  !mysql_query($sql, $cxn_mcl)  ) 
		{
			trigger_error('Could not execute query: ' . $sql . '; ' . mysql_error($cxn_mcl), E_USER_ERROR);
			exit();
		}

		// If email works, then save the user verification record
		$sql = 
			'insert into user_validation (uid, email_sent, hash) values (' .
				"'" . mysql_real_escape_string($user->uid, $cxn_mcl) . "', " .
				'NOW(), ' .
				"'" . mysql_real_escape_string($hash, $cxn_mcl) . "'" . 
			')';
		if (  !mysql_query($sql, $cxn_mcl)  ) 
		{
			trigger_error('Could not create verification record--you will need to sign up again: ' . $sql . '; ' . mysql_error($cxn_mcl), E_USER_ERROR);
			exit();
		}

		return true;
	}

	/**
	 * Validate an email address. Provide email address (raw input). Returns true 
	 * if the email address has the email address format and the domain exists.
	 */
	public function isValidEmail($email)
	{
		$isValid = true;
		$atIndex = strrpos($email, "@");
		if (is_bool($atIndex) && !$atIndex) 
		{
			$isValid = false;
		} 
		else 
		{
			$domain    = substr($email, $atIndex+1);
			$local     = substr($email, 0, $atIndex);
			$localLen  = strlen($local);
			$domainLen = strlen($domain);
			if ($localLen < 1 || $localLen > 64) {
				// local part length exceeded
				$isValid = false;
			} else if ($domainLen < 1 || $domainLen > 255) {
				// domain part length exceeded
				$isValid = false;
			} else if ($local[0] == '.' || $local[$localLen-1] == '.') {
				// local part starts or ends with '.'
				$isValid = false;
			} else if (preg_match('/\\.\\./', $local)) {
				// local part has two consecutive dots
				$isValid = false;
			} else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
				// character not valid in domain part
				$isValid = false;
			} else if (preg_match('/\\.\\./', $domain)) {
				// domain part has two consecutive dots
				$isValid = false;
			} else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
				// character not valid in local part unless local part is quoted
				if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
					$isValid = false;
				}
			}
			/* - getting rid of this so that it works with localhost only
			 * 
			if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
				// domain not found in DNS
				$isValid = false;
			}
			*/
		}
		return $isValid;
	}
}

?>