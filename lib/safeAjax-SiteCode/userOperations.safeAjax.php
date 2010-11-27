<?php

require_once(dirname(__FILE__)."/safeAjax_SiteCode.interface.php"); 

// This drop-in is catching requests for protected resources

class userOperations implements safeAjax_SiteCode {
	function handleRequest ( &$params, &$json, &$auth ) {

		//----------------------------------------------------
		// given a [ new(username, email, password) ] if [ email, username ] not-exist then create user with access 2 (unconfirmed)
		if (array_key_exists('registerUser',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				$newAccess = NEW_USER_ACCESS; // self-registering users have a default access level
				if (array_key_exists('passwordSha1',$params) && ($params['passwordSha1'] != 0)) { $params['newPassword'] = sha1($params['newPassword']); }
				if ($newUserData = $auth->createUser($params['newEMail'],$params['newUsername'],$params['newPassword'],$newAccess)) {
					$json['auth'] = $newUserData;
					// load the template for '...'
					// send the email for '...'
				} else {
					$json['authErr'] = $auth->lastError();
					$json['auth'] = null; 
				}
			}
		}

		//----------------------------------------------------
		// given a [ new(username, email, password) ] if [ email, username ] not-exist then create user with access-1 (unconfirmed)
		if (array_key_exists('delegateCreate',$params)) {
			if ($auth->testAccessLevel(1,$json)) {
				$newAccess = $auth->data[sa_access] -1; // new delegates have an access value one less than the creator
				if (array_key_exists('passwordSha1',$params) && ($params['passwordSha1'] != 0)) { $params['newPassword'] = sha1($params['newPassword']); }
				if ($newUserData = $auth->createUser($params['newEMail'],$params['newUsername'],$params['newPassword'],$newAccess)) {
					$json['auth'] = $newUserData;
					// load the template for '...'
					// send the email for '...'
				} else {
					$json['authErr'] = $auth->lastError();
					$json['auth'] = null; 
				}
			}
		}

		//----------------------------------------------------
		// given a [ existing(username, email) ] if [ email, username ] exist then delete user
		if (array_key_exists('delegateDelete',$params)) {
			if ($auth->testAccessLevel(1,$json)) {
				if ($deleteeViaEMail = $auth->getViaIndex(ndx_userEMail,$params['newEMail'],DoNotLoad)) {
					if ($deleteeViaUsername = $auth->getViaIndex(ndx_userLogin,$params['newUsername'],DoNotLoad)) {
						if ($deleteeViaEMail[sa_userId] == $deleteeViaUsername[sa_userId]) {
							if ($auth->deleteUser($deleteeViaEMail[sa_userId])) {
								$json['auth'] = true;
							} else {
								$json['authErr'] = $auth->lastError();
								$json['auth'] = false; 
							}
						} else {
							$json['authErr'] = " that email is not registered to that username ";
							$json['auth'] = false; 
						}
					} else {
						$json['authErr'] = " user not found via username";
						$json['auth'] = false; 
					}
				} else {
					$json['authErr'] = " user not found via email";
					$json['auth'] = false; 
				}
			}
		}

		//----------------------------------------------------
		// given a [ existing(email) ] if [ email ] exist then email username
		if (array_key_exists('userForgotten',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				if ($userViaEMail = $auth->getViaIndex(ndx_userEMail,$params['newEMail'],DoNotLoad)) {

					// email the username to this email address
					$json['username'] = $userViaEMail[sa_username];
					// for this TEST ONLY return the username 

				} else {
					$json['authErr'] = " user not found via email";
					$json['auth'] = false; 
				}
			}
		}

		//----------------------------------------------------
		// given a [ existing(email + username) ] if [ email + username ] exist then return hash
		if (array_key_exists('forgotPassword',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				if ($pwdResetHash = $auth->getPasswordResetHash($params['newEMail'],$params['newUsername'])) {
					$json['resetHash'] = $pwdResetHash;
				} else {
					$json['authErr'] = $auth->lastError();
					$json['auth'] = false; 
				}
			}
		}

		//----------------------------------------------------
		// given a [ existing(hash + password) ] if [ hash ] matches then reset
		if (array_key_exists('passwordResetByHash',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				if ($auth->updatePasswordViaHash($params['passwordHash'],$params['newPassword'])) {
					$json['passwordReset'] = "password was reset";
				} else {
					$json['authErr'] = " could not update password, error: ".$auth->lastError();
					$json['auth'] = false; 
				}
			}
		}
		
		//----------------------------------------------------
		// given a [ existing(hash + password) ] if [ hash ] matches then reset
		if (array_key_exists('updateViaIndex',$params)) {
			if ($auth->testAccessLevel(1,$json)) {
				if ($auth->updateIndexedValue($auth->userField2Index($params['updateViaIndex']),$params['updateViaIndex'],$params['newValue'],$auth->getUserId())) {
					$json['auth'] = $auth->data; 
				} else {
					$json['authErr'] = " could not update userdata, error: ".$auth->lastError();
					$json['auth'] = null; 
				}
			}
		}
		
		//----------------------------------------------------
		// given a [ existing(hash + password) ] if [ hash ] matches then reset
		if (array_key_exists('updateWithoutIndex',$params)) {
			if ($auth->testAccessLevel(1,$json)) {
				if ($auth->updateDataValue($params['updateWithoutIndex'],$params['newValue'],$auth->getUserId())) {
					$json['auth'] = $auth->data; 
				} else {
					$json['authErr'] = " could not update userdata, error: ".$auth->lastError();
					$json['auth'] = null; 
				}
			}
		}
	
	}
}

?>
