<?php
ob_start();

//	safeAjax - connector creds checker
//
//	I wrote this connector to safeguard my ajax communications.  
//
//	It's originally based upon chris shifletts article: the truth about sessions 
//
//	changelog, see allen @joslin .net for changes
//
//		version 4.5: cloned from SafeAjaxAuthenticator to add user maintenance options
//		version 4: changes for code re-arrangements, added cookieValue creation
//    version 3: rebuilt cookies for adjustable duration
//	   version 2.1: combine reader & writer into one object
//	   version 2.0: rewrite/refactor, support accessLevels and registration
//	   version 1.0: code collection from dev/test/debug
//
//             - SafeAjaxAuthenticator: base class, no access allowed by default

require_once(dirname(__FILE__)."/Access_Levels.php"); 

// NOTE execute the dbSpecific subclass from the command line to manually create users -- creating the 1st user must be done this way...  (see EOF for details.)

DEFINE('sa_userId','userId');					// userId for this record -- from uuid()
DEFINE('sa_creatorId','creatorId');			// userId that created this record
DEFINE('sa_email','email');					// email assoc with this userId
DEFINE('sa_username','username');			// username assoc with this userId
DEFINE('sa_password','password');			// password assoc with this userId
DEFINE('sa_access','access');					// access level assoc with this userId
DEFINE('sa_persistence','persistence');	// persistence assoc with this userId
DEFINE('sa_resetPwdHash','resetPwdHash');	// resetPwdHash assoc with this userId

DEFINE('db_userData','userData');			// the main user "table"

DEFINE('ndx_userEMail','userEMail');					// the "index" for access via email
DEFINE('ndx_userLogin','userLogin');					// the "index" for access via username
DEFINE('ndx_userResetHash','userResetHash');			// the "index" for access via password reset hash
DEFINE('ndx_userPersistence','userPersistence');	// the "index" for access via persistence

DEFINE('action_create','createUser');
DEFINE('action_modify','modifyUser');
DEFINE('action_delete','deleteUser');

DEFINE('LoadData',true);
DEFINE('DoNotLoad',false);

DEFINE('SelfUpdate',true);
DEFINE('NotSelfUpdate',false);

DEFINE('UpdateViaIndex',true);
DEFINE('UpdateNotIndexed',false);

// ------------------------
class SafeAjaxUserData {
	
	var $isReady;
	var $hasData;
	var $lastErr;
	var $dbDebug;
	var $dbConn;
	var $data;

	// ----------------     construct destruct    -----------------------------------------
	// ------------------------------------------------------------------------------------
	function __construct ( $optional=null ) { 
		$this->setReady(false); $this->lastErr = ""; 
		$this->dbDebug = null; $this->dbConn = null;
		$this->data = null; $this->hasData = false;
	}

	// ------------------------------------------------------------------------------------
	function __destruct () { 
	}
	
	// ----------------     getters    ----------------------------------------------------
	// ------------------------------------------------------------------------------------
	function isReady () { return $this->isReady; }
	function setReady ( $newValue )	{ $this->isReady = $newValue; }
	
	function lastError () { return $this->lastErr; }
	function setLastError ( $newValue ) { $this->lastErr = $newValue; }
	
	function hasData () { return $this->hasData; }
	function setHasData ( $newValue ) { $this->hasData = $newValue; }

	function getCreator ()      { return (empty($this->data))? null: (array_key_exists(sa_creator      ,$this->data))? $this->data[sa_creator]      :null; } 
	function getUserId ()       { return (empty($this->data))? null: (array_key_exists(sa_userId       ,$this->data))? $this->data[sa_userId]       :null; } 
	function getEMail ()        { return (empty($this->data))? null: (array_key_exists(sa_email        ,$this->data))? $this->data[sa_email]        :null; } 
	function getUsername ()     { return (empty($this->data))? null: (array_key_exists(sa_username     ,$this->data))? $this->data[sa_username]     :null; } 
	function getPassword ()     { return (empty($this->data))? null: (array_key_exists(sa_password     ,$this->data))? $this->data[sa_password]     :null; } 
	function getAccessLevel ()  { return (empty($this->data))? null: (array_key_exists(sa_access       ,$this->data))? $this->data[sa_access]       :null; } 
	function getPersistence ()  { return (empty($this->data))? null: (array_key_exists(sa_persistence  ,$this->data))? $this->data[sa_persistence]  :null; } 
	function getPwdResetHash () { return (empty($this->data))? null: (array_key_exists(sa_resetPwdHash ,$this->data))? $this->data[sa_resetPwdHash] :null; } 

	// ------------------------------------------------------------------------------------
	// protect a string -- against attempted embedded database manipulations
	function dbSafe ( $inputStr ) {
		return (get_magic_quotes_gpc())? $inputStr: addslashes((String)$inputStr);
	}

	// ------------------------------------------------------------------------------------
	// do we support the requested functionality ? 
	function canDo_forgotUsername () { return false; } // requires an email-address to send the username to 
	function canDo_forgotPassword () { return false; } // requires a username to get the email-address to send the password to 
	function canDo_resetPassword () { return false; } // requires a username to get the email-address to send the passwordResetHash to

	// ------------------------------------------------------------------------------------
	// generate a unique id -- (multiple generations in the same microsecond on syncronized machines could overlap)
	function uuid () {
		return (uniqid('',true));
	}

	// ------------------------------------------------------------------------------------
	// map the index to the associated userField
	function index2UserField ( $indexId ) {
		$transformed = '';
		switch ($indexId) {
			case ndx_userEMail: { $transformed = sa_email; break; }
			case ndx_userLogin: { $transformed = sa_username; break; }
			case ndx_userResetHash: { $transformed = sa_resetPwdHash; break; }
			case ndx_userPersistence: { $transformed = sa_persistence; break; }
		}
		return $transformed;
	}

	// ------------------------------------------------------------------------------------
	// map the userfields to the matching indexes
	function userField2Index ( $userField ) {
		$transformed = '';
		switch ($userField) {
			case sa_email: { $transformed = ndx_userEMail; break; }
			case sa_username: { $transformed = ndx_userLogin; break; }
			case sa_resetPwdHash: { $transformed = ndx_userResetHash; break; }
			case sa_persistence: { $transformed = ndx_userPersistence; break; }
		}
		return $transformed;
	}

	// ------------------------------------------------------------------------------------
	// -- this function THROWS -- this function THROWS -- this function THROWS -- this function THROWS --
	// given a [ action, proposedAccess ] check to be sure this action is allowed
	function actionAllowed ( $action, $proposedAccess ) {
		switch ($action) {
			case action_create: {
				if ($this->hasData()) {
					if ($this->getAccessLevel() < $proposedAccess) { 
						throw new Exception("you cannot create a user above your own access level"); 
					}
				}
				break;
			}
			case action_modify: {
				if (! $this->hasData()) { 
					throw new Exception("you must be logged in to modify a user"); 
				}
				if ($this->getAccessLevel() < $proposedAccess) { 
					throw new Exception("you cannot promote a user above your own access level"); 
				}
				break;
			}
			case action_delete: {
				if (! $this->hasData()) { 
					throw new Exception("you must be logged in to delete a user"); 
				}
				if ($this->getAccessLevel() < $proposedAccess) { 
					throw new Exception("you cannot delete a user above your own access level"); 
				}
				break;
			}
		}
	}

	// ------------------------------------------------------------------------------------
	// -- this function THROWS -- this function THROWS -- this function THROWS -- this function THROWS --
	// given a [ newKey, newValue ] check to be sure this update is allowed
	function updateAllowed ( $newKey, $newValue, $selfUpdate, $indexedUpdate ) {
		switch ($newKey) {
			case sa_access: {
				if ($indexedUpdate == UpdateViaIndex) {
					throw new Exception("you cannot change an acess level as an indexed value"); 
				}
				if ($selfUpdate) {
					throw new Exception("you cannot change your own access level"); 
				} else {
					$this->actionAllowed(action_modify,$newValue);
				}
				break;
			}
			case sa_password: {
				if ($indexedUpdate == UpdateViaIndex) {
					throw new Exception("you cannot change a password value as an indexed value"); 
				}
				break;
			}
			case sa_userId: {
				throw new Exception("you cannot change a userId"); 
				break;
			}
			case sa_creatorId: {
				throw new Exception("you cannot change a creatorId"); 
				break;
			}
			case sa_email: {
				if ($indexedUpdate == UpdateNotIndexed) {
					throw new Exception("you cannot change a email value without updating the index as well"); 
				}
				break;
			}
			case sa_username: {
				if ($indexedUpdate == UpdateNotIndexed) {
					throw new Exception("you cannot change a username value without updating the index as well"); 
				}
				break;
			}
			case sa_persistence: {
				if ($indexedUpdate == UpdateNotIndexed) {
					throw new Exception("you cannot change a persistence value without updating the index as well"); 
				}
				if (! $selfUpdate) {
					throw new Exception("you cannot change the persistence level for someone else"); 
				}
				break;
			}
			case sa_resetPwdHash: {
				if ($indexedUpdate == UpdateNotIndexed) {
					throw new Exception("you cannot change a passwordResetHash value without updating the index as well"); 
				}
				if ($selfUpdate) {
					throw new Exception("you cannot change your own passwordResetHash level"); 
				}
				break;
			}
		}
	}


	// ----------------     userdata    ---------------------------------------------------
	// ------------------------------------------------------------------------------------
	function getViaUserId ( $allegedUserId, $loadUserData ) {
		$this->setReady(false); $this->setLastError("error: getViaUserId method not overridden");
	}

	// ------------------------------------------------------------------------------------
	// given a [ value ] return/load associated user data record
	function getViaIndex ( $indexId, $allegedKey, $loadUserData ) {
		$this->setReady(false); $this->setLastError("error: getViaIndex method not overridden");
	}

	// ------------------------------------------------------------------------------------
	// given a [ newKey, newValue ] if hasData() then update the storage and reload
	function updateDataValue ( $newKey, $newValue, $updateeId ) {
		$this->setReady(false); $this->setLastError("error: updateDataValue method not overridden");
	}

	// ------------------------------------------------------------------------------------
	// given a [ indexId, newKey, newValue ] if hasData() then update the index and the storage and reload
	function updateIndexedValue ( $indexId, $newKey, $newValue, $updateeId ) {
		$this->setReady(false); $this->setLastError("error: updateIndexedValue method not overridden");
	}

	// ------------------------------------------------------------------------------------
	// return pwdChangeHash
	function getPasswordResetHash ( $resetViaEMail, $resetViaUsername ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }
			
			$resetUserFromEMail = $this->getViaIndex(ndx_userEMail,$resetViaEMail,DoNotLoad);
			if (! $resetUserFromEMail) { throw new Exception(" could not identify user via email"); }

			$resetUserFromLogin = $this->getViaIndex(ndx_userLogin,$resetViaUsername,DoNotLoad);
			if (! $resetUserFromLogin) { throw new Exception(" could not identify user via username"); }

			if ($resetUserFromEMail[sa_userId] != $resetUserFromLogin[sa_userId]) { throw new Exception("userIdFromEMail not equal to userIdFromLogin "); }

			// generate the reset hash
			$newPasswordResetHash = $this->uuid(); 

			if ($this->updateIndexedValue(ndx_userResetHash,sa_resetPwdHash,$newPasswordResetHash,$resetUserFromLogin[sa_userId])) {

				// we are good
				$this->setReady(true);

				// return the hash
				return $newPasswordResetHash;
			}
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ------------------------------------------------------------------------------------
	// return pwdChangeHash
	function updatePasswordViaHash ( $passwordResetHash, $newPassword ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }
			
			$userToReset = $this->getViaIndex(ndx_userResetHash,$passwordResetHash,DoNotLoad);
			if (! $userToReset) { throw new Exception(" could not identify user via resetHash"); }

			if ($this->updateDataValue(sa_password,$newPassword,$userToReset[sa_userId])) {
				
				$ignored = $this->updateIndexedValue(ndx_userResetHash,sa_resetPwdHash,$this->uuid(),$userToReset[sa_userId]);

				// we are good
				$this->setReady(true);
				
				return true;
			}
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return false;
	}

	// ------------------------------------------------------------------------------------
	// given a [ UserId ] if [ access is < then requestor ] then delete
	function deleteUser ( $allegedDeleteUserId ) {
		$this->setReady(false); $this->setLastError("error: deleteUser method not overridden");
	}

	// ------------------------------------------------------------------------------------
	// given a [ username, email, password, access ] if [ EMail, username ] not-exist then store
	function createUser ( $allegedEMail, $allegedUsername, $allegedPassword, $allegedAccess ) {
		$this->setReady(false); $this->setLastError("error: createUser method not overridden");
	}

	// ----------------     creds    ------------------------------------------------------
	// ------------------------------------------------------------------------------------
	// given a username and password it checks for validity NOTE: this function MUST be overridden in the usage class
	function testLoginCreds ( $allegedUsername, $allegedPassword, &$json  ) {
		return $this->accessDenied($json);
	}

	// ----------------     access    -----------------------------------------------------
	// ------------------------------------------------------------------------------------
	// NOTE: this function MUST be overridden in the usage class
	function testAccessLevel ( $requestedLevel, &$json ) {
		return $this->accessDenied($json);
	}

	// ------------------------------------------------------------------------------------
	// grant access
	function accessGranted ( &$json ) {
		$json['authRequires'] = "Resource Access Granted.";		
		$json['authOK'] = true;
		return true;
	}

	// ------------------------------------------------------------------------------------
	// deny access
	function accessDenied ( &$json ) {
		$json['authRequires'] = "Login is required to access this resource.";		
		$json['authOK'] = false;
		return false;
	}

	// ----------------     cookies    ----------------------------------------------------
	// ------------------------------------------------------------------------------------
	function makeCookieValue ( $duration ) { 
		// this cookie is good for the duration (by a well-behaved browser), 
		//	  until the session changes, or until midnight -- whichever comes first
		$today = getdate(); $salt = $today['yday'] . $duration;
		return sha1($salt . session_id() . $_SERVER['HTTP_USER_AGENT']);
	}

	// ------------------------------------------------------------------------------------
	// this tests cookie values against a predictable value
	function tasteCookie ( $requestedCookieName, $requestedCookieDuration ) {
		if (empty($_COOKIE[$requestedCookieName])) { return false; }
		return ($this->makeCookieValue($requestedCookieDuration) != $_COOKIE[$requestedCookieName])? false: true;
	}

	// ------------------------------------------------------------------------------------
	// this returns the value from a named cookie
	function getACookie ( $theCookieName ) {
		if (! isset($_COOKIE[$theCookieName])) { return false; }
		return (isset($_COOKIE[$theCookieName]))? $_COOKIE[$theCookieName]: "null";
	}

	// ------------------------------------------------------------------------------------
	// this writes cookie values based upon defined requested types
	function bakeCookie ( $requestedCookieDomain, $requestedCookiePath, $requestedCookieName, $requestedCookieDuration ) {
		setcookie($requestedCookieName, $this->makeCookieValue($requestedCookieDuration), time() + $requestedCookieDuration, $requestedCookiePath, ".".$requestedCookieDomain);
	}

}

// THIS SCRIPT SHOULD BE UNCOMMENTED AND TESTED FOR EACH LANGUAGE SPECIFIC SUBCLASS
// running this script from the command line will create the desired user if not existing already
//if (! empty($argc)) {
//	$usageStr = "usage: ".$argv[0]." <email> <username> <password> <access>\n";
//	if ($argc != 5) {
//		print $usageStr;
//	} else {
//		$email = $argv[1]; $username = $argv[2]; 
//		$password = $argv[3]; $access = $argv[4];
//		if (empty($access) || (! empty($argv[5]))) {
//			print $usageStr;
//		} else {
//			try {
//				$creator = new UserData();
//				$creator->hasData = true;
//				$creator->getAccessLevel() = $access;
//				$creator->getUserId() = $creator->uuid();
//				$creator->createUser($email,$username,$password,$access);
//			}
//			catch (Exception $ex) {
//				print("exception: ".$ex->getMessage());
//			}
//		}
//	}
//}

ob_end_flush();
?>