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
	public function isValid(MclUser $user, array &$arr_err = null)
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
	 * Validate an email address. Provide email address (raw input). Returns true 
	 * if the email address has the email address format and the domain exists.
	 */
	function isValidEmail($email)
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