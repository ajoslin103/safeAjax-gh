<?php
ob_start();
session_start();

// allen @joslin .com	
// 
// this code connects the client-side with the server-side codebases
//  	the inputs are merged from all GET and POST param arrays
// 	the outputs are sent back as a JSON structure
//
//		return contains:
//			[boolean]         safeAjax: required creds supplied (includes no creds required)
//			[string]  safeAjaxRequires: request for creds, suitable for use in login dialogs
//			[string]     safeAjaxError: safeAjax systems internal error description, if any
// 
// 	version 4.5.1 from safeAjax v4.5 - added server-side action log, will be returned if sa_Debug is true
// 	version 4.5 from safeAjax v4.1 - added [UserData] user maintenance functions
// 	version 4.1 from safeAjax v4 - added startup & shutdown hook-files, add nodelist to ViaCassandra Authenticators
// 	version 4 from safeAjax v3 - added MongoDB & Cassandra database support, moved dbConn into database support files, rearranged everything into folders
// 	version 3 from safeAjax v3 - added cookieTest
// 	version 3 from safeAjax v3 - added debug & cookieDuration params
// 	version 2.1 from safeAjax v2 - merged reader and writer into dbConn
// 	version 2.0 from safeAjax v1 - collected and described from usage/upgrades

$params = array_merge($_GET,$_POST); // these are the inputs
$json = array(); $json['connectorVersion'] = 4.51; // these are the outputs

$json['sa_ActionLog'] = array(); // logging server-side actions, will only be returned if sa_Debug = true

$json['thisServer'] = $_SERVER['HTTP_HOST'];
$json['thisProtocol'] = $_SERVER['SERVER_PROTOCOL'];

// import logically important items from the browser side

$json['uuid'] = (! empty($params['sa_Debug'])) ? $params['uuid'] : 'none'; // this is the uuid for this request-reponse

$db_clientDebug = (! empty($params['sa_Debug'])) ? $params['sa_Debug'] : false;
$db_cookieTime = (! empty($params['sa_Duration'])) ? countSeconds($params['sa_Duration']) : countSeconds("24h");

$json['sa_Debug'] = $db_clientDebug;
$json['sa_Duration'] = $db_cookieTime;

$json['sha1_inUse'] = (! empty($params['sha1Logins'])) ? $params['sha1Logins'] : false;

$json['sa_Username'] = (! empty($params['unFieldName'])) ? $params['unFieldName'] : "sa_UName";
$json['sa_Password'] = (! empty($params['pwFieldName'])) ? $params['pwFieldName'] : "sa_PWord";

// set your desired cookie information, the domain & path defaults to the entire server

$json['sa_CookieDomain'] = $json['thisServer'];
$json['sa_CookieName'] = "sacv5";
$json['sa_CookiePath'] = "/";

// instantiate ONLY your desired authentication style
// maintain your login creds within these files/databases

// --> --> select a UserData style <-- <-- 
// require_once(dirname(__FILE__)."/Authenticators/UserData.ViaCassandra.php"); // accepts = cassandra nodeList = hash ( 'name' => port, 'name' => port )
// require_once(dirname(__FILE__)."/Authenticators/UserData.ViaMongodb.php"); // accepts = mongodb nodeList = [ < v1.8 ] scalar( 'name', 'name' ) [ > v1.8 ] scalar ( 'name:port', 'name:port' )
require_once(dirname(__FILE__)."/Authenticators/UserData.ViaMySQL.php"); // accepts = mysql nodeName 'name' 
// require_once(dirname(__FILE__)."/Authenticators/UserData.ViaSQLite.php"); // accepts = sqlite 'dbPath' 
// require_once(dirname(__FILE__)."/Authenticators/UserData.ViaCode_UnPw.php");
// require_once(dirname(__FILE__)."/Authenticators/UserData.ViaCode_MMDD.php");

try { 

	// --> --> select a UserData creation parameter <-- <-- 
	// $userDataParm = null; // UnPw & MMDD style -- ignored
	$userDataParm = 'localHost'; // mysql style, string: hostName
	// $userDataParm = array( 'localhost' ); // mongo style, scalar until > v1.8
	// $userDataParm = array( 'localhost' => 9160 ); // cassandra style, hash, available hosts: node => port 
	// $userDataParm = dirname(dirname(__FILE__)).'/sqlite/safeAjax.sqlite'; // sqlite style, string: path2db

	// instantiate UserData
	$authenticatorObj = new UserData($userDataParm); 

	if ($authenticatorObj == null) { throw new Exception("safeAjax internal error: 'authenticatorObj' did not instantiate"); }
	if (! $authenticatorObj->isReady()) { throw new Exception("safeAjax internal error: authenticatorObj: ".$authenticatorObj->lastError()); }
	
	// if this is a logout request we do it now
	if ((! empty($params['sacv_cookieVoid']))? $params['sacv_cookieVoid'] : false) {

		$json['logout'] = "logged out";
		session_regenerate_id();
		$json['authOK'] = true;
		session_destroy();

	}

	// if this is a "logged-in" query we do it now
	if ((! empty($params['sacv_cookieGood']))? $params['sacv_cookieGood'] : false) {

		// anything over 0 requires a login 
		if ($authenticatorObj->testAccessLevel(1,$json)) {
			$json['loggedIn'] = " user is logged in";
		} else {
			$json['loggedIn'] = " not logged in";
		}

	}

	// if this is a login request we do it now
	if ((! empty($params['sacv_cookieTest']))? $params['sacv_cookieTest'] : false) {

		// the frontend of safeAjax tells us in which fields we will find the username/password 
		$uname = (! empty($params[$params['unFieldName']]))? $params[$params['unFieldName']]: "";
		$pword = (! empty($params[$params['pwFieldName']]))? $params[$params['pwFieldName']]: "";
		$ignored = $authenticatorObj->testLoginCreds($uname,$pword,$json); 
		
		// pass back the fact that this was a login request
		$json['sacv_cookieTest'] = 1;
	}

	if (! array_key_exists('authOK',$json)) {

		// find our site-specific code
		$dbmsClassDir = "../safeAjax-SiteCode";
		if (! is_dir("$dbmsClassDir")) { throw new Exception("safeAjax internal error: '$dbmsClassDir' is not a directory"); }

		// give the startup file the first shot
		if (file_exists($dbmsClassDir."/safeAjax_requestStart.php")) {
			require_once($dbmsClassDir."/safeAjax_requestStart.php"); 
			try { safAjax_Startup($params,$json,$authenticatorObj); } 
			catch (Exception $ex) { $json['sa_startup_exception'] = $ex->getMessage(); }
		}

		// get all the files in the ./safeAjax-SiteCode folder, named like this: xxxxx.class.php
		$allegedClasses = dirList($dbmsClassDir,".safeAjax.php");
		if (! count($allegedClasses)) { throw new Exception("safeAjax unexpected: '$dbmsClassDir' contains no files"); }

		// loop the found files
		$json['safeDBMSClasses'] = array();
		foreach ($allegedClasses as $alleged) {

			try {

				// include and instantiate each as an object
				require("$dbmsClassDir/$alleged");
				$class = substr($alleged,0,strpos($alleged,"."));
				if (! class_exists($class)) { throw new Exception("safeAjax unexpected: '$alleged' file does not define $class"); } 

				$object = new $class; // give that object a chance at the request
				if (! ($object instanceof safeAjax_SiteCode)) { throw new Exception("safeAjax unexpected: '$class' file doesn't implement safeAjax_SiteCode"); }

				array_push($json['safeDBMSClasses'],"$class");
				$object->handleRequest($params,$json,$authenticatorObj);
			}

			catch (Exception $e) {
				// print "<!-- " .$e->getMessage(). " -->";
				$json['safeAjaxError'] = $e->getMessage();
			}
		}

		// give the shutdown file the last shot
		if (file_exists($dbmsClassDir."/safeAjax_requestStop.php")) {
			require_once($dbmsClassDir."/safeAjax_requestStop.php"); 
			try { safAjax_Shutdown($params,$json,$authenticatorObj); } 
			catch (Exception $ex) { $json['sa_shutdown_exception'] = $ex->getMessage(); }
		}

		if (! array_key_exists('authOK',$json)) { 
			throw new Exception("safeAjax unexpected: this request was not handled by any installed site-code");
		}

		// at the end of it all, if we are not debugging then 
		if (! $json['sa_Debug']) {

			// clear out the helpers we loaded into the output block
			unset($json['connectorVersion']);
			unset($json['thisServer']);
			unset($json['thisProtocol']);
			unset($json['sa_Debug']);
			unset($json['sa_Duration']);
			unset($json['sha1_inUse']);
			unset($json['sa_Username']);
			unset($json['sa_Password']);
			unset($json['sa_CookieDomain']);
			unset($json['sa_CookieName']);
			unset($json['sa_CookiePath']);
			unset($json['safeDBMSClasses']);
			unset($json['sa_ActionLog']);
		}
	}
}

catch (Exception $e) {
	// print "<!-- " .$e->getMessage(). " -->";
	$json['safeAjaxError'] = $e->getMessage();
	$json['authOK'] = true; // prevent authRequest from looping forever
}

// utility: get a list of the files in a given directory that have the given name fragment
function dirList ($directory,$nameFrag) {
	$results = array();
	$handler = opendir($directory);
	while ($file = readdir($handler)) {
		if ($file == '.' || $file == '..') { continue; }
		if (strpos($file,$nameFrag) === false) { continue; }
		$results[] = $file;
	}
	closedir($handler);
	return $results;
}

// utility: turn a time description into a number of seconds
function countSeconds ($timeDesc) {
	$seconds = preg_split('/[a-z]+/', $timeDesc);
	switch ($timeDesc[strlen($timeDesc)-1]) {
		case 'm': $seconds = $seconds[0] *= 60; break;
		case 'h': $seconds = $seconds[0] *= (60*60); break;
		case 'd': $seconds = $seconds[0] *= (60*60*24); break;
		default: $seconds = $seconds[0]; break;
	}
	return $seconds;
}
	
// return localTime as passed from the client or whatever it is now on the server
function safeAjax_LocalTime ( $params ) {
	return array_key_exists('sa_localTime',$params)? $params['sa_localTime']: date('r',time());
}

// return localTimeOffset as passed from the client or 0
function safeAjax_LocalOffset ( $params ) {
	return array_key_exists('sa_localOffset',$params)? $params['sa_localOffset']: 0;
}

// add a line to the action log
function safeAjax_LogAction ( $msg ) {
	global $json;
	array_push($json['sa_ActionLog'],$msg);
}

print json_encode($json);
ob_end_flush();
?>