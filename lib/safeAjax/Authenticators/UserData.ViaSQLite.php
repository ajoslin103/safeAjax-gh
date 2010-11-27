<?php
ob_start();

// NOTE execute this file from the command line to manually create users -- creating the 1st user must be done this way...  (see EOF for details.)

require_once(dirname(__FILE__)."/UserData.interface.php"); 

// CREATE TABLE IF NOT EXISTS `tbl_users` (
//   `id` int(11) NOT NULL auto_increment,
//   `userId` varchar(32) NOT NULL default '',
//   `creatorId` varchar(32) NOT NULL default '',
//   `access` int(11) NOT NULL default '0',
//   `email` varchar(128) NOT NULL default '',
//   `username` varchar(32) NOT NULL default '',
//   `password` varchar(64) NOT NULL default '',
//   `persistence` varchar(64) NOT NULL default '',
//   `resetPwdHash` varchar(32) NOT NULL default '',
//   PRIMARY KEY  (`id`)
// ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

DEFINE('db_path',dirname(dirname(dirname(__FILE__))).'/sqlite/safeAjax.sqlite');

class UserData extends SafeAjaxUserData {
	
	var $sqlCache;
	
	// -------------------------------------------------
	function __construct ( $nodePath=db_path ) {

		try {

			parent::__construct();

			// init our dbConn as an array
			$this->dbConn = array();

			// create the sqlilite file
			$oldErrorLevel = error_reporting(0); // for can't create the database file
			$this->dbConn[db_userData] = sqlite_open($nodePath,0666,$this->lastErr);
			error_reporting($oldErrorLevel);
			if (! $this->dbConn[db_userData]) {
				if (! file_exists($nodePath)) { 
					throw new Exception("could not create SQLite datafile: ".$nodePath); 
				} else {
					throw new Exception("could not use SQLite: ".$this->lastError()); 
				}
			}
			
			// create the table, ignore errors
			$sqlColumnArr = array();
			array_push($sqlColumnArr," userId varchar(32) ");
			array_push($sqlColumnArr," creatorId varchar(32) ");
			array_push($sqlColumnArr," access int(11) ");
			array_push($sqlColumnArr," email varchar(128) ");
			array_push($sqlColumnArr," username varchar(32) ");
			array_push($sqlColumnArr," password varchar(64) ");
			array_push($sqlColumnArr," persistence varchar(6432) ");
			array_push($sqlColumnArr," resetPwdHash varchar(32) ");
			$createTableSQL = "CREATE TABLE tbl_users (".implode(',',$sqlColumnArr).")";
			$oldErrorLevel = error_reporting(0); // for table_already_exists
			sqlite_query($this->dbConn[db_userData],$createTableSQL);
			error_reporting($oldErrorLevel);

			$this->setReady(true); // good to go
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}
	}

	// -------------------------------------------------
	function __destruct () {
	}

	// ------------------------------------------------------------------------------------
	// -- this function THROWS -- this function THROWS -- this function THROWS -- this function THROWS --
	// given a [ newKey, newValue ] check to be sure this update is allowed
	function updateAllowed ( $newKey, $allegedValue, $selfUpdate, $updateIndexed ) {

		// -- this function THROWS -- this function THROWS -- this function THROWS --
		parent::updateAllowed($newKey,$allegedValue,$selfUpdate,$updateIndexed); // check with your parents

		// all known columns we are ok with
		// (exceptions were already thrown
		// on any of these that mattered)
		switch ($newKey) {
			case sa_email:			case sa_username:
			case sa_password:		case sa_userId:
			case sa_creatorId:	case sa_access:
			case sa_persistence:	case sa_resetPwdHash:
			{ break; }
			
			default: {
				// everything except known columns
				throw new Exception("sqlite cannot handle unknown column names"); 
				break;
			}
		}
	}

	// ----------------     userdata    ---------------------------------------------------
	// ------------------------------------------------------------------------------------
	// given a [ userId ] return/load associated user data
	function getViaUserId ( $allegedUserId, $loadUserData ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }

			$safeUserId = $this->dbSafe($allegedUserId);

			// load and test the record
			$selectSQL = " select * from tbl_users where ".sa_userId." = '".$safeUserId."'";
			$selectResult = sqlite_query($this->dbConn[db_userData],$selectSQL);
			if ($selectResult == null) { throw new Exception("error: could not build query in: getViaUserId"); }
			$dataCache = sqlite_fetch_array($selectResult,SQLITE_ASSOC);
			if ($dataCache == null) { throw new Exception("error: user not found for userId: $safeUserId"); }
			if (! count($dataCache)) { throw new Exception("internal error: empty object found for userId: $safeUserId"); }

			// replace our guts if requested
			if ($loadUserData) { $this->data = $dataCache; $this->setHasData(true); }

			// we are good
			$this->setReady(true);

			// give back what we found
			return $dataCache;
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ------------------------------------------------------------------------------------
	// given a [ value ] return/load associated user data record
	function getViaIndex ( $indexId, $allegedKey, $loadUserData ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }

			$safeValue = $this->dbSafe($allegedKey);

			// sql can search into the record, so we can convert the indexName into a fieldName
			$safeKey = $this->index2UserField($this->dbSafe($indexId)); 
			if (empty($safeKey)) { throw new Exception(" cannot find the index for the given key "); }

			// get the userId from the record
			$selectSQL = " select ".sa_userId." from tbl_users where ".$safeKey." = '".$safeValue."'";
			$selectResult = sqlite_query($this->dbConn[db_userData],$selectSQL);
			if ($selectResult == null) { throw new Exception("error: could not build query in: getViaIndex"); }
			if ($userIdFromIndex = sqlite_fetch_array($selectResult,SQLITE_ASSOC)) {
				return $this->getViaUserId($userIdFromIndex[sa_userId],$loadUserData);
			}

			if ($loadUserData) {
				throw new Exception("requested index key not found"); // load + not found = error
			} else {
				return null; // no load + not found = no error
			}
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ------------------------------------------------------------------------------------
	// given a [ newKey, newValue ] if hasData() then update the storage and reload
	function updateDataValue ( $newKey, $newValue, $updateeId ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }
			
			$safeKey = $this->dbSafe($newKey);
			$safeValue = $this->dbSafe($newValue);
			
			// check if this update is allowed -- this function THROWS -- this function THROWS -- this function THROWS
			$this->updateAllowed($safeKey, $safeValue, (($updateeId == $this->getUserId())? SelfUpdate: NotSelfUpdate), UpdateNotIndexed); 

			// update and return ourselves
			$updateSQL = " update tbl_users set ".$safeKey." = '".$safeValue."' where ".sa_userId." = '".$updateeId."'";
			$updateResult = sqlite_query($this->dbConn[db_userData],$updateSQL);
			return $this->getViaUserId($updateeId,($updateeId == $this->getUserId())? LoadData: DoNotLoad);
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ------------------------------------------------------------------------------------
	// given a [ indexId, newKey, newValue ] if hasData() then update the index and the storage and reload
	function updateIndexedValue ( $indexId, $newKey, $newValue, $updateeId ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }
			
			$safeKey = $this->dbSafe($newKey);
			$safeValue = $this->dbSafe($newValue);
			
			// check if this update is allowed -- this function THROWS -- this function THROWS -- this function THROWS
			$this->updateAllowed($safeKey, $safeValue, (($updateeId == $this->getUserId())? SelfUpdate: NotSelfUpdate), UpdateViaIndex); 

			// check that we are not going to clobber an existing [unique] index value
			$selectSQL = " select ".sa_userId." from tbl_users where ".$safeKey." = '".$safeValue."'";
			$selectResult = sqlite_query($this->dbConn[db_userData],$selectSQL);
			if ($selectResult == null) { throw new Exception("error: could not build query in: updateIndexedValue"); }
			if ($userIdFromIndex = sqlite_fetch_array($selectResult,SQLITE_ASSOC)){
				throw new Exception("cannot replace/orphan an existing index row/value ");
			}

			// update and return ourselves
			$updateSQL = " update tbl_users set ".$safeKey." = '".$safeValue."' where ".sa_userId." = '".$updateeId."'";
			$updateResult = sqlite_query($this->dbConn[db_userData],$updateSQL);
			return $this->getViaUserId($updateeId,($updateeId == $this->getUserId())? LoadData: DoNotLoad);
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ------------------------------------------------------------------------------------
	// given a [ UserId ] if [ access is < then requestor ] then delete
	function deleteUser ( $allegedDeleteUserId ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }
			if (! $this->hasData()) { throw new Exception("object not ready in ".__FILE__." at line ". __LINE__); }

			$safeDeleteUserId = $this->dbSafe($allegedDeleteUserId);
			if ($safeDeleteUserId == $this->getUserId()) { throw new Exception(" you cannot delete yourself "); }
			
			$userToDelete = $this->getViaUserId($safeDeleteUserId,DoNotLoad);
			
			if (empty($userToDelete)) {
				return false; // no id + delete = no error
			}
			
			// check if this update is allowed -- this function THROWS -- this function THROWS -- this function THROWS
			$this->actionAllowed(action_delete,$userToDelete[sa_access]);
								
			// delete data record
			$deleteSQL = " delete from tbl_users where ".sa_userId." = '".$userToDelete[sa_userId]."'";
			$deleteResult = sqlite_query($this->dbConn[db_userData],$deleteSQL);
			
			return true; // no err
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return false;
	}

	// ------------------------------------------------------------------------------------
	// given a [ username, email, password, access ] if [ EMail, username ] not-exist then store
	function createUser ( $allegedEMail, $allegedUsername, $allegedPassword, $allegedAccess ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }

			$safeEMail = $this->dbSafe($allegedEMail); if (! $safeEMail) { throw new Exception("an email is required to create a user."); }
			$safeUsername = $this->dbSafe($allegedUsername); if (! $safeUsername) { throw new Exception("a username is required to create a user."); }
			$safePassword = $this->dbSafe($allegedPassword); if (! $safePassword) { throw new Exception("a password is required to create a user."); }
			$safeAccess = $this->dbSafe($allegedAccess); if (! $safeAccess) { throw new Exception("an access value is required to create a user."); }

			// check if this update is allowed -- this function THROWS -- this function THROWS -- this function THROWS
			$this->actionAllowed(action_create,$allegedAccess);
			
			// we will not create a user if the username or email is already in use
			$selectSQL = " select count(*) as inUseAlready from tbl_users where ".sa_username." = '".$safeUsername."' or ".sa_email." = '".$safeEMail."'";
			$selectResult = sqlite_query($this->dbConn[db_userData],$selectSQL);
			if ($selectResult == null) { throw new Exception("error: could not build query in: getViaIndex"); }
			if ($valuesInUseCount = sqlite_fetch_array($selectResult,SQLITE_ASSOC)) {
				if ($valuesInUseCount['inUseAlready'] > 0) {
					throw new Exception("email or username already in use");
				}
			}

			// generate the new userId
			$newUserId = $this->uuid(); 
			
			$columns = sa_userId .",". sa_email .",". sa_username .",". sa_password .",". sa_access;
			$insertClause = "'$newUserId', '$safeEMail', '$safeUsername', '$safePassword', $safeAccess";
			
			if ($this->hasData()) {
				$columns .= ",". sa_creatorId;
				$insertClause .= ",'". $this->getUserId() ."'";
			}

			// insert into the database
			$updateSQL = " insert into tbl_users ($columns) values ( $insertClause )";
			$updateResult = sqlite_query($this->dbConn[db_userData],$updateSQL);			

			return $this->getViaUserId((String)$newUserId,DoNotLoad);
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ----------------   testing  creds    ------------------------------------------------------
	// ------------------------------------------------------------------------------------
	// if the given creds are good then allow access -- value's can [&should] arrive sha1(encrypted)
	function testLoginCreds ( $allegedUsername, $allegedPassword, &$json ) {

		if ($this->dbConn != null) {

			$json['dbDebug'] = $this->dbDebug;

			if ($this->tasteCookie($json['sa_CookieName'],$json['sa_Duration'])) {

				$json['freshCookie'] = false;
				$json['tastedCookie'] = $json['sa_CookieName'];
				return $this->accessGranted($json);

			} else {

				$safeUsername = $this->dbSafe($allegedUsername); 
				$safePassword = $this->dbSafe($allegedPassword);

				if (! empty($json['sha1Logins'])) {
					$safeUsername = ($json['sha1Logins'])? sha1($safeUsername): $safeUsername;
					$safePassword = ($json['sha1Logins'])? sha1($safePassword): $safePassword;
				}

				try {

					if ($allegedUser = $this->getViaIndex(ndx_userLogin,$safeUsername,DoNotLoad)) {

						if ($allegedUser[sa_password] == "$safePassword") {

							$this->getViaUserId($allegedUser[sa_userId],LoadData);

							$newPersistence = $this->makeCookieValue($json['sa_Duration']);
							$json['newPersistence'] = $newPersistence;

							try {

								$this->updateIndexedValue(ndx_userPersistence,sa_persistence,$newPersistence,$this->getUserId());

								try {

									$this->bakeCookie($json['sa_CookieDomain'],$json['sa_CookiePath'],$json['sa_CookieName'],$json['sa_Duration']);
									$json['bakedCookie'] = $json['sa_CookieName'];
									$json['freshCookie'] = true;

									return $this->accessGranted($json);
								}

								catch (Exception $ex) { $json['cassErr'] = "tlc-03: ". $ex->getMessage(); }

							}

							catch (Exception $ex) { $json['cassErr'] = "tlc-02: ". $ex->getMessage(); }
						}
					}
				}

				catch (Exception $ex) { $json['cassErr'] = "tlc-01: ". $ex->getMessage(); }
			}
		}

		return parent::testLoginCreds($allegedUsername,$allegedPassword,$json);
	}

	// ------------------------------------------------------------------------------------
	// check access level 
	function testAccessLevel ( $requestedLevel, &$json ) {

		$json['dbDebug'] = $this->dbDebug;

		if ($requestedLevel == 0) { return $this->accessGranted($json); }
		if ($this->dbConn != null) {

			if ($this->tasteCookie($json['sa_CookieName'],$json['sa_Duration'])) {

				if ($allegedUserLevelCookie = $this->getACookie($json['sa_CookieName'])) {

					$safeUserLevelCookie = $this->dbSafe($allegedUserLevelCookie);

					try {

						if ($allegedUser = $this->getViaIndex(ndx_userPersistence,$safeUserLevelCookie,DoNotLoad)) {

							if ($requestedLevel < $allegedUser[sa_access]) { 

								$this->getViaUserId($allegedUser[sa_userId],LoadData); // internalize

								$json['freshCookie'] = false;
								$json['tastedCookie'] = $json['sa_CookieName'];
								return $this->accessGranted($json);
							}
						}
					}

					catch (Exception $ex) { $json['cassErr'] = "tal-01: ". $ex->getMessage(); }
				}
			}

			return $this->accessDenied($json);
		}
	}

}

// ------------------------------------------------------------------------------------
// ------------------------------------------------------------------------------------

// 
// done
// 

// running this script from the command line will create the desired user if not existing already
if (! empty($argc)) {
	if (! function_exists('sqlite_query')) {
		print " Config Error: the sqlite extension is not loaded for cmd line usage, \n   use 'php --ini' to determine which .ini file your cmdline is using \n   use find / -type f -name php to see if you have more than one version installed \n";
	} else {
		$usageStr = "usage: ".$argv[0]." <email> <username> <password> <access>\n";
		if ($argc != 5) {
			print $usageStr;
		} else {
			$email = $argv[1]; $username = $argv[2]; 
			$password = $argv[3]; $access = $argv[4];
			if (empty($access) || (! empty($argv[5]))) {
				print $usageStr;
			} else {
				try {
					$creator = new UserData();
					if (! $creator->isReady()) { throw new Exception($creator->lastError()); }
					$creator->hasData = true;
					$creator->data[sa_access] = $access;
					$creator->data[sa_userId] = $creator->uuid();
					$creator->createUser($email,$username,$password,$access);
				}
				catch (Exception $ex) {
					print("exception: ".$ex->getMessage()."\n");
					if (! file_exists(dirname(db_path))) {
						print("   do you need to create the directory: ".dirname(db_path)."?\n");
					} else {
						print("   do you need to set permissions on the directory: ".dirname(db_path)."?\n");
					}
				}
			}
		}
	}
}

ob_end_flush();
?>