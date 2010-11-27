<?php
ob_start();

require_once(dirname(__FILE__)."/UserData.interface.php"); 

//	safeAjax - connector creds checker class
//
//		version 4: changes for code re-arrangements
//    version 3: rebuilt for adjustable duration 
//	   version 2.1: combine reader & writer into one object
//	   version 2.0: rewrite/refactor, support accessLevels and registration
//	   version 1.0: code collection from dev/test/debug
//
//             - SafeAjaxAuthenticator: base class, no access allowed by default
//             - PasswordViaCode_UnPw: code-based, username & password
//             - PasswordViaCode_MMDD: code-based, username & username+MMDD
//             - PasswordViaMySQL: database-based, user-based w/access-levels
//             - PasswordViaMongoDB: database-based, user-based w/access-levels
//             - PasswordViaCassandra: database-based, user-based w/access-levels

// ------------------------
class UserData extends SafeAjaxUserData {
	
	// ------------------------------------------------------------------------------------
	function __construct ( $ignored="" ) {
		parent::__construct();
		$this->setReady(true); // false by default
	}
	
	// ------------------------------------------------------------------------------------
	// protect the resource using simple words -- value's can [and should] arrive sha1(encrypted)
	function simpleSafety ( $allegedUsername, $allegedPassword, &$json )
	{
		$desiredUsername = ""; // sample only
		$desiredPassword = "safeAjax"; // sample only
		
		$unameOK = false; $pwordOK = false; 
		
		if ($json['sha1_inUse'] == "false") {
			$unameOK = true; //(0 == strcmp($allegedUsername,""))? true: false; 
			$pwordOK = (0 == strcmp($allegedPassword,"safeAjax"))? true: false; 
		} else {
			$unameOK = true; //(0 == strcmp($allegedUsername,sha1("")))? true: false; 
			$pwordOK = (0 == strcmp($allegedPassword,sha1("safeAjax")))? true: false; 
		}

		return ($unameOK && $pwordOK);
	}

	// ------------------------------------------------------------------------------------
	// if the given creds are good then allow access
	function testLoginCreds ( $allegedUsername, $allegedPassword, &$json )
	{
		if ($this->tasteCookie($json['sa_CookieName'],$json['sa_Duration'])) {

			$json['freshCookie'] = false;
			$json['tastedCookie'] = $json['sa_CookieName'];
			return $this->accessGranted($json);

		} else {

			if ($this->simpleSafety($allegedUsername,$allegedPassword,$json)) {

				$this->bakeCookie($json['sa_CookieDomain'],$json['sa_CookiePath'],$json['sa_CookieName'],$json['sa_Duration']);
				$json['bakedCookie'] = $json['sa_CookieName'];
				$json['freshCookie'] = true;

				return $this->accessGranted($json);
			}

		}

		return parent::testLoginCreds($allegedUsername,$allegedPassword,$json);
	}

	// ------------------------------------------------------------------------------------
	// check access level  
	function testAccessLevel ( $requestedLevel, &$json )
	{
		switch ($requestedLevel) {
			case 0: { // users
				return $this->accessGranted($json);
				break;
			}
			default: { // admins
				if ($this->tasteCookie($json['sa_CookieName'],$json['sa_Duration'])) {
					$json['tastedCookie'] = $json['sa_CookieName'];
					$json['freshCookie'] = false;
					return $this->accessGranted($json);
				}
				break;
			}
		}
		
		return parent::testAccessLevel($requestedLevel,$json);
	}

}

// ------------------------------------------------------------------------------------
// ------------------------------------------------------------------------------------

// 
// done
// 

ob_end_flush();
?>