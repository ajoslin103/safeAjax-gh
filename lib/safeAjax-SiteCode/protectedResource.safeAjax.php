<?php

require_once("../safeAjax-SiteCode/safeAjax_SiteCode.interface.php"); // cwd is ./safeAjax/

// This drop-in is catching requests for protected resources

class protectedResource implements safeAjax_SiteCode {

	function handleRequest ( &$params, &$json, &$auth ) {

		if (! empty($params['protect'])) { //----------------------------------------------------
			if ($params['protect'] == 'sampleRequest') {
				if ($auth->testAccessLevel(1,$json)) {

					$json['returnedResource'] = " this string is protected by a login";
				}
			}

		}

	}

}

?>
