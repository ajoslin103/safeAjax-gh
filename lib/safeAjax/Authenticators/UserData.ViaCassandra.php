<?php
ob_start();

// NOTE execute this file from the command line to manually create users -- creating the 1st user must be done this way...  (see EOF for details.)

$GLOBALS['THRIFT_ROOT'] = dirname(dirname(dirname(__FILE__))).'/phpcassa-0.1/thrift';
require_once $GLOBALS['THRIFT_ROOT'].'/packages/cassandra/Cassandra.php';
require_once $GLOBALS['THRIFT_ROOT'].'/packages/cassandra/cassandra_types.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TSocket.php';
require_once $GLOBALS['THRIFT_ROOT'].'/protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TFramedTransport.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TBufferedTransport.php';
require_once(dirname($GLOBALS['THRIFT_ROOT'])."/phpcassa.php"); 

require_once(dirname(__FILE__)."/UserData.interface.php"); 
require_once(dirname(__FILE__)."/Cassandra.CF_Index.php"); 

//	<Keyspace Name="safeAjax">
// 	<ColumnFamily Name="userData" CompareWith="UTF8Type" />			<!-- key: userId, values: access, username, password, persistence, email, roles -->
// 	<ColumnFamily Name="userEMail" CompareWith="UTF8Type" />		<!-- key: EMail, values: userId -->
// 	<ColumnFamily Name="userLogin" CompareWith="UTF8Type" />		<!-- key: username, values: userId -->
// 	<ColumnFamily Name="userResetHash" CompareWith="UTF8Type" />	<!-- key: passwdResetHash, values: userId -->
// 	<ColumnFamily Name="userPersistence" CompareWith="UTF8Type" />	<!-- key: persistence, values: userId -->
//	</Keyspace>

DEFINE('ViaCassandra_Keyspace','safeAjax');

class UserData extends SafeAjaxUserData {

	// -------------------------------------------------
	function __construct ( $nodeArray=array( 'localhost' => '9160' ) ) {

		try {

			parent::__construct();

			// init the database connection
			foreach ( array_keys($nodeArray) as $nodeName ) {
				CassandraConn::add_node($nodeName,$nodeArray[$nodeName]);
			}

			// init our dbConn as an array
			$this->dbConn = array();

			// our primary Column Family
			$this->dbConn[db_userData] = new CassandraCF(ViaCassandra_Keyspace, db_userData);
			if (empty($this->dbConn[db_userData])) { throw new Exception("main column family not ready"); }
			
			// the email index
			$this->dbConn[ndx_userEMail] = new CF_Index(ViaCassandra_Keyspace, ndx_userEMail, sa_userId);
			if (! $this->dbConn[ndx_userEMail]->isReady()) { throw new Exception($this->dbConn[ndx_userEMail]->lastError()); }
			
			// the username index
			$this->dbConn[ndx_userLogin] = new CF_Index(ViaCassandra_Keyspace, ndx_userLogin, sa_userId);
			if (! $this->dbConn[ndx_userLogin]->isReady()) { throw new Exception($this->dbConn[ndx_userLogin]->lastError()); }
			
			// the resetHash index
			$this->dbConn[ndx_userResetHash] = new CF_Index(ViaCassandra_Keyspace, ndx_userResetHash, sa_userId);
			if (! $this->dbConn[ndx_userResetHash]->isReady()) { throw new Exception($this->dbConn[ndx_userResetHash]->lastError()); }
			
			// the persistence index
			$this->dbConn[ndx_userPersistence] = new CF_Index(ViaCassandra_Keyspace, ndx_userPersistence, sa_userId);
			if (! $this->dbConn[ndx_userPersistence]->isReady()) { throw new Exception($this->dbConn[ndx_userPersistence]->lastError()); }

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

	// ----------------     userdata    ---------------------------------------------------
	// ------------------------------------------------------------------------------------
	// given a [ userId ] return/load associated user data
	function getViaUserId ( $allegedUserId, $loadUserData ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }

			$safeUserId = $this->dbSafe($allegedUserId);

			// load and test the record
			$dataCache = $this->dbConn[db_userData]->get((String)$safeUserId);
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

			$safeKey = $this->dbSafe($allegedKey);

			// if we can find a match in the index, then go get the record
			if ($userIdFromIndex = $this->dbConn[$indexId]->getRemoteKey((String)$safeKey)) {
				return $this->getViaUserId($userIdFromIndex,$loadUserData);
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
	// given a [ newKey, newValue ] if hasData() then update the storage
	function updateDataValue ( $newKey, $newValue, $updateeId ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }
			
			$safeKey = $this->dbSafe($newKey);
			$safeValue = $this->dbSafe($newValue);
			
			// check if this update is allowed, will protect the indexes -- this function THROWS -- this function THROWS -- this function THROWS
			$this->updateAllowed($safeKey, $safeValue, (($updateeId == $this->getUserId())? SelfUpdate: NotSelfUpdate), UpdateNotIndexed); 

			// update and return the record
			$this->dbConn[db_userData]->insert((String)$updateeId, array( $safeKey => $safeValue ));
			return $this->getViaUserId($updateeId,(($updateeId == $this->getUserId())? LoadData: DoNotLoad));
		}

		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}

		return null;
	}

	// ------------------------------------------------------------------------------------
	// given a [ indexId, newKey, newValue ] if hasData() then update the index and the storage
	function updateIndexedValue ( $indexId, $newKey, $newValue, $updateeId ) {
		try {
			if (! $this->isReady()) { throw new Exception($this->lastError()." [database not ready in ".__FILE__." at line ". __LINE__."]"); }

			$safeKey = $this->dbSafe($newKey);
			$safeValue = $this->dbSafe($newValue);
			
			if (empty($this->dbConn[$indexId])) { throw new Exception(" the requested index does not exist"); }
			
			// check if this update is allowed -- this function THROWS -- this function THROWS -- this function THROWS
			$this->updateAllowed($safeKey, $safeValue, (($updateeId == $this->getUserId())? SelfUpdate: NotSelfUpdate), UpdateViaIndex); 
			
			// check that we are not going to clobber an existing [unique] index value
			if ($this->dbConn[$indexId]->getRemoteKey((String)$safeValue)) {
				throw new Exception("cannot replace/orphan an existing index row/value ");
			}

			// update the external index include the oldValue (if any) to be removed from the index
			$preExistingData = $this->getViaUserId($updateeId,DoNotLoad); // using the record we are targeting for the update to get the old value
			$this->dbConn[$indexId]->insertIndexRow((String)$safeValue,(String)$updateeId,((array_key_exists($safeKey,$preExistingData))?$preExistingData[$safeKey]:""));

			// update the record we are targeting for the update
			$this->dbConn[db_userData]->insert((String)$updateeId, array( $safeKey => $safeValue ));
			return $this->getViaUserId($updateeId,(($updateeId == $this->getUserId())? LoadData: DoNotLoad));
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
			
			// delete the index values -- persistence and reset hash might not exist -- we'll check 'em all
			if (array_key_exists(sa_email,$userToDelete)) { $this->dbConn[ndx_userEMail]->deleteIndexRow($userToDelete[sa_email]); }
			if (array_key_exists(sa_username,$userToDelete)) { $this->dbConn[ndx_userLogin]->deleteIndexRow($userToDelete[sa_username]); }
			if (array_key_exists(sa_persistence,$userToDelete)) { $this->dbConn[ndx_userPersistence]->deleteIndexRow($userToDelete[sa_persistence]); }
			if (array_key_exists(sa_resetPwdHash,$userToDelete)) { $this->dbConn[ndx_userResetHash]->deleteIndexRow($userToDelete[sa_resetPwdHash]); }

			// delete data record
			$this->dbConn[db_userData]->remove($userToDelete[sa_userId]);
			
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
			if ($this->dbConn[ndx_userEMail]->getRemoteKey((String)$safeEMail)) { throw new Exception("email already in use"); }
			if ($this->dbConn[ndx_userLogin]->getRemoteKey((String)$safeUsername)) { throw new Exception("username already in use"); }

			// generate the new userId
			$newUserId = $this->uuid(); 

			// insert the key row(s) in the main data column family
			$this->dbConn[db_userData]->insert((String)$newUserId, array(sa_userId => $newUserId));
			if ($this->hasData()) { $this->dbConn[db_userData]->insert((String)$newUserId, array(sa_creatorId => $this->getUserId())); }

			// insert the dataRows
			$this->dbConn[db_userData]->insert((String)$newUserId, array(sa_email => $safeEMail));
			$this->dbConn[db_userData]->insert((String)$newUserId, array(sa_access => $safeAccess));
			$this->dbConn[db_userData]->insert((String)$newUserId, array(sa_username => $safeUsername));
			$this->dbConn[db_userData]->insert((String)$newUserId, array(sa_password => $safePassword));

			// update the email index
			$this->dbConn[ndx_userEMail]->insertIndexRow((String)$safeEMail, (String)$newUserId);
			if (! $this->dbConn[ndx_userEMail]->isReady()) { throw new Exception($this->dbConn[ndx_userEMail]->lastError()); }

			// update the login index
			$this->dbConn[ndx_userLogin]->insertIndexRow((String)$safeUsername, (String)$newUserId);
			if (! $this->dbConn[ndx_userLogin]->isReady()) { throw new Exception($this->dbConn[ndx_userLogin]->lastError()); }

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

						if ($allegedUser = $this->getViaIndex(ndx_userPersistence,(String)$safeUserLevelCookie,DoNotLoad)) {

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
				$creator->hasData = true;
				$creator->data[sa_access] = $access;
				$creator->data[sa_userId] = $creator->uuid();
				$creator->createUser($email,$username,$password,$access);
			}
			catch (Exception $ex) {
				print("exception: ".$ex->getMessage());
			}
		}
	}
}

ob_end_flush();
?>