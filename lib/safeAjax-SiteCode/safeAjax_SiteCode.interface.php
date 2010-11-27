<?php

// allen @joslin .net
// 
// This is the interface for database access classes, drop in your classes as siblings
// 	and implement this interface.  The dbConnector.php will instantiate each
// 	class in this folder and give each one a chance at the request.
// 
// 	version 2.2 from safeAjax v4 - commenting for code re-arrangements
// 	version 2.1 from safeAjax v2 - merged reader and writer into dbConn
// 	version 2.0 from safeAjax v1 - collected and described from usage/upgrades
// 

interface safeAjax_SiteCode {
	
	public function handleRequest ( &$params, &$json, &$dbmsConnector );
	
}

// sample usage, using access levels of 0=readOnly and 5=readWrite -- see dbmsConnector for more details
//		this example uses MySQL database access helpers -- see helpers_MySQL.php for usage
//
//	
//		function handleRequest ( &$params, &$json, &$dbmsConnector ) {
//
//			// DATA ----------------------------------------------------------------------------------
//			if ($params['data'] == "someObjectType") { //---------------------------------------------
//				if ($dbmsConnector->testAccessLevel(0,$json)) {
//					$selectSQL = " select * from tbl_someTable where id = ".mysql_real_escape_string($params['id'],$dbConn->conn($json));
//					safeAjax_LogAction($selectSQL);
//					if ($selectResult = $dbConn->db_select_json($selectSQL,$json)) {
//						if ($eachRow = mysql_fetch_assoc($selectResult)) {
//							foreach (array_keys($eachRow) as $colName) { 
//								$json[$colName] = $eachRow[$colName];
//							} 
//						}
//					}
//				}
//			}
//
//			// DATA ----------------------------------------------------------------------------------
//			if ($params['enable'] == "someObjectType") { //-------------------------------------------
//				if ($dbmsConnector->testAccessLevel(5,$json,$dbConn)) {
//					$updateSQL = " update tbl_someTable set enabled=1 where id = ".mysql_real_escape_string($params['id'],$dbConn->conn($json));
//					safeAjax_LogAction($updateSQL);
//					$selectResult = $dbConn->db_update_json($selectSQL,$json);
//				}
//			}
//		
//		}

?>
