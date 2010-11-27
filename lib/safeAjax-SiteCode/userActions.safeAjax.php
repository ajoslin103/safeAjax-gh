<?php

require_once(dirname(__FILE__)."/safeAjax_SiteCode.interface.php"); 

// This drop-in is catching requests for protected resources

class userActions implements safeAjax_SiteCode {
	function handleRequest ( &$params, &$json, &$auth ) {

		//------------------------------- FINDING USERS ----------------------------------
		//----------------------------------------------------
		// given a [ userId ] if exists then return associated record
		if (array_key_exists('findViaUserId',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				$json['auth'] = $auth->getViaUserId($params['findViaUserId'],DoNotLoad);
				// load the template for '...'
				// send the email for '...'
			}
		}

		//----------------------------------------------------
		// given a [ email ] if exists then return associated record
		if (array_key_exists('findViaEmail',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				$json['auth'] = $auth->getViaIndex(ndx_userEMail,$params['findViaEmail'],DoNotLoad);
				// load the template for '...'
				// send the email for '...'
			}
		}


		//----------------------------------------------------
		// given a [ username ] if exists then return associated record
		if (array_key_exists('findViaUsername',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				$json['auth'] = $auth->getViaIndex(ndx_userLogin,$params['findViaUsername'],DoNotLoad);
				// load the template for '...'
				// send the email for '...'
			}
		}

		//----------------------------------------------------
		// given a [ persistence ] if exists then return associated record
		if (array_key_exists('findViaPersistence',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				$json['auth'] = $auth->getViaIndex(ndx_userPersistence,$params['findViaPersistence'],DoNotLoad);
				// load the template for '...'
				// send the email for '...'
			}
		}

		//----------------------------------------------------
		// given a [ resetHash ] if exists then return associated record
		if (array_key_exists('findViaPwdResetHash',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				$json['auth'] = $auth->getViaIndex(ndx_userResetHash,$params['findViaPwdResetHash'],DoNotLoad);
				// load the template for '...'
				// send the email for '...'
			}
		}

		//------------------------------- UPDATING USERS ----------------------------------
		//----------------------------------------------------
		// given a [ userId's & arbitraryKey/Value ] if allowed then update & return associated record
		if (array_key_exists('updateArbitrary',$params)) {
			$updatorId = (array_key_exists('updatorId',$params))? $params['updatorId']: null;
			$updateeId = (array_key_exists('userId',$params))? $params['userId']: null;
			if ((! $updatorId) && (! $updateeId)) { 
				$json['authErr'] = " you must specify the user to update at a minimum ";
				$json['auth'] = null;
			}
			// check that the target of the update exists, can also be the initiator of the update
			$user = $auth->getViaUserId($updateeId,LoadData);
			if (! $user) {
				$json['authErr'] = "updatee: ".$auth->lastError();
				$json['auth'] = null;
			}
			// check that the initiator of the update exists (optional)
			if ($updatorId) {
				$user = $auth->getViaUserId($updatorId,LoadData);
				if (! $user) {
					$json['authErr'] = "updator: ".$auth->lastError();
					$json['auth'] = null;
				}
			}
			if ($auth->testAccessLevel(1,$json)) {
				// update without affecting any of the index's
				if (! $auth->updateDataValue($params['updateArbitrary'],$params['updateValue'],$updateeId)) {
					$json['authErr'] = $auth->lastError();
					$json['auth'] = null;
				} else {
					$json['auth'] = $auth->getViaUserId($updateeId,($updatorId == $updateeId)? LoadData: DoNotLoad); 
				}
				// load the template for '...'
				// send the email for '...'
			}
		}

		//----------------------------------------------------
		// given a [ UserId & Access ] if exists then update & return associated record
		if (array_key_exists('updateIndexed',$params)) {
			$updatorId = (array_key_exists('updatorId',$params))? $params['updatorId']: null;
			$updateeId = (array_key_exists('userId',$params))? $params['userId']: null;
			if ((! $updatorId) && (! $updateeId)) { 
				$json['authErr'] = " you must specify the user to update at a minimum ";
				$json['auth'] = null;
			}
			// check that the target of the update exists, can also be the initiator of the update
			$user = $auth->getViaUserId($updateeId,LoadData);
			if (! $user) {
				$json['authErr'] = "updatee: ".$auth->lastError();
				$json['auth'] = null;
			}
			// check that the initiator of the update exists (optional)
			if ($updatorId) {
				$user = $auth->getViaUserId($updatorId,LoadData);
				if (! $user) {
					$json['authErr'] = "updator: ".$auth->lastError();
					$json['auth'] = null;
				}
			} else { 
				$updatorId == $updateeId; 
			} // if there is no updator then we are the updator
			if ($auth->testAccessLevel(1,$json)) {
				// determine the index name
				switch ($params['updateIndexed']) {
					case sa_email: { $indexName = ndx_userEMail; break; }
					case sa_username: { $indexName = ndx_userLogin; break; }
					case sa_resetPwdHash: { $indexName = ndx_userResetHash; break; }
					case sa_persistence: { $indexName = ndx_userPersistence; break; }
					default: { $indexName = 'unknown_index'; break; }
				}
				// update with consideration for the index's
				if (! $auth->updateIndexedValue($indexName,$params['updateIndexed'],$params['updateValue'],$updateeId)) {
					$json['authErr'] = $auth->lastError();
					$json['auth'] = null;
				} else {
					$json['auth'] = $auth->getViaUserId($updateeId,(($updatorId == $updateeId)? LoadData: DoNotLoad)); 
				}
				// load the template for '...'
				// send the email for '...'
			}
		}

		//------------------------------- PASSWORD RESET HASH ----------------------------------
		//----------------------------------------------------
		// given a [ UserId & Password ] if exists then update & return associated record
		if (array_key_exists('getPasswordResetHash',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				if (! $auth->getViaUserId($params['getPasswordResetHash'],LoadData)) {
					$json['auth'] = null;
				} else {
					if (! $auth->getPasswordResetHash()) {
						$json['authErr'] = $auth->lastError();
						$json['auth'] = null;
					} else {
						$json['auth'] = $auth->data;
					}
				}
				// load the template for '...'
				// send the email for '...'
			}
		}

		//------------------------------- DELETING USERS ----------------------------------
		//----------------------------------------------------
		// given a [ deletorId & deleteUserId ] if deletor exists attempt delete of deleteuserId
		if (array_key_exists('deleteUser',$params)) {
			if ($auth->testAccessLevel(1,$json)) {
				if (! $auth->getViaUserId($params['deletorId'],LoadData)) {
					$json['authErr'] = "could not locate deletor record";
					$json['auth'] = null;
				} else {
					if (! $auth->deleteUser($params['deleteUserId'])) {
						$json['authErr'] = $auth->lastError();
						$json['auth'] = false; 
					} else {
						$json['auth'] = true;
						// load the template for '...'
						// send the email for '...'
					}
				}
			}
		}

		//------------------------------- CREATING USERS ----------------------------------
		//----------------------------------------------------
		// given a [ creatorId, & new(username, email, password, access) ] if [ email, username ] not-exist then create user
		if (array_key_exists('createUser',$params)) {
			if ($auth->testAccessLevel(1,$json)) {
				$json['passwordSha1'] = $params['passwordSha1'];
				if ($params['passwordSha1'] != 0) { $params['newPassword'] = sha1($params['newPassword']); }
				if (! $auth->getViaUserId($params['creatorId'],LoadData)) {
					$json['authErr'] = "could not locate creator record";
					$json['auth'] = null;
				} else {
					if ($newUserData = $auth->createUser($params['newEMail'],$params['newUsername'],$params['newPassword'],$params['newAccess'])) {
						$json['auth'] = $newUserData;
						// load the template for '...'
						// send the email for '...'
					} else {
						$json['authErr'] = $auth->lastError();
						$json['auth'] = null; 
					}
				}
			}
		}

	}
}

?>
