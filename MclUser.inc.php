<?php

/**
 * MCL User Object
 * Note that this object requires that the session be started to work
 */
class MclUser 
{
	public $user_id  =  null;
	public $uid      =  null;
	public $fname    =  null;
	public $lname    =  null;
	public $org      =  null;
	public $super    =  null;

	public function __construct($user_id, $uid, $fname, $lname, $org, $super) 
	{
		$this->user_id  =  $user_id;
		$this->uid      =  $uid;
		$this->fname    =  $fname;
		$this->lname    =  $lname;
		$this->org      =  $org;
		$this->super    =  $super;
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
		$uid = strtolower($uid);
		$sql = 
				"select user_id, email, fname, lname, org, super from mcl.user where LOWER(email) = '" . 
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
					$row[  'super'    ]
				);
			return $user;
		}
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
	public function isValidPassword($pwd) {
		if (strlen($pwd) >= 4) return true;
		return false;
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