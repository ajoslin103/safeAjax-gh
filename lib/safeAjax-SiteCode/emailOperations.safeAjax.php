<?php

require_once(dirname(__FILE__)."/safeAjax_SiteCode.interface.php"); 
require_once(dirname(dirname(__FILE__))."/email/email.classes.php"); 

// This drop-in is handling requests for email operations

class emailOperations implements safeAjax_SiteCode {
	function handleRequest ( &$params, &$json, &$auth ) {

		// $params['email_name']
		// $params['email_domain']
		// $params['email_addr']
		// $params['email_to']
		// $params['email_cc']
		// $params['email_bcc']
		// $params['email_subject']
		// $params['email_body']
		// $params['email_substitutions']

		//----------------------------------------------------
		if (array_key_exists('getEMailHeader',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				try {
					
					$email = new EMailHandler($params); // this constructor THROWS 
					
					$json['EMailHandler-result'] = $email->buildHeader();
				}
				catch (Exception $ex) {
					$json['EMailHandler-error'] = $ex->getMessage();
				}
			}
		}

		//----------------------------------------------------
		if (array_key_exists('getEMail',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				try {
					
					$email = new EMailHandler($params); // this constructor THROWS 
					
					$json['EMailHandler-result'] = $email->buildEMail($params); // this function THROWS
				}
				catch (Exception $ex) {
					$json['EMailHandler-error'] = $ex->getMessage();
				}
			}
		}

		//----------------------------------------------------
		if (array_key_exists('sendEMail',$params)) {
			if ($auth->testAccessLevel(0,$json)) {
				try {
					
					$email = new EMailHandler($params); // this constructor THROWS 
					$email->buildEMail($params); // this function THROWS 
					
					$json['EMailHandler-result'] = $email->sendEMail($params); // this function THROWS 
				}
				catch (Exception $ex) {
					$json['EMailHandler-error'] = $ex->getMessage();
				}
			}
		}

	}
}

?>
