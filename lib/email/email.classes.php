<?php

/**
*
*	EMailHandler 
*
*		a class for templatizing and transmitting email
*
**/

class EMailHandler {
	
	const email_name          = 'email_name';     // the name of the sender
	const email_domain        = 'email_domain';   // the domain the email originates from 
	const email_addr          = 'email_addr';     // the address of the sender
	
	const email_to            = 'email_to';       // the recipient address(es) [string(delim=,) or scalar(a1,a2,a3)]
	const email_cc            = 'email_cc';       // the carbon-copy recipient address(es) [string(delim=,) or scalar(a1,a2,a3)]
	const email_bcc           = 'email_bcc';      // the blind-carbon-copy recipient address(es) [string(delim=,) or scalar(a1,a2,a3)]
	
	const email_substitutions = 'email_substitutions'; // a hash of substitutions to apply (to subject and body)
	const email_subject       = 'email_subject';  // the subject of the email
	const email_body          = 'email_body';     // the body of the email
	
	const email_result        = 'email_result';   // the result of the last sendEMail action
	
	const eol = "\r\n";
	
	var $data;
	
	// --------------------------------------------------------------------------------------------------------
	// this constructor THROWS -- this constructor THROWS -- this constructor THROWS
	function __construct ( $params ) {
		
		$this->data = array();
		
		$keysWanted = array(  // build an intersector
			self::email_name => 1
			,self::email_domain => 1
			,self::email_addr => 1
		);

		// take only what we want from the given params
		$this->data = array_merge(array_intersect_key($params,$keysWanted),$this->data);
		
		// take some standards from the server, if they were not passed in already
		if (empty($this->data[self::email_domain])) { $this->data[self::email_domain] = $_SERVER['HTTP_HOST']; }
		
		// if the associated [from] address contains an @ then explode into an addr & domain
		if (strpos($this->data[self::email_addr],'@') !== false) {
			$arr = explode('@',$this->data[self::email_addr]);
			if ($arr[1] != $this->data[self::email_domain]) { throw new Exception("the given domain [".$this->data[self::email_domain]."] does not equal the [optional] domain of the from address: ".$arr[1]); }
			$this->data[self::email_domain] = $arr[1];
			$this->data[self::email_addr] = $arr[0];
		}
		
		// check those things we cannot do without at this time
		// if (empty($)) { throw new Exception("  is empty in EMailHandler::__constructor "); }
		if (empty($this->data[self::email_domain])) { throw new Exception(" email_domain was empty in EMailHandler::__constructor "); }
		if (empty($this->data[self::email_addr])) { throw new Exception(" email_addr was empty in EMailHandler::__constructor "); }
		
		return true;
	}
	
	// --------------------------------------------------------------------------------------------------------
	// process the subject and body using any given substitutions -- this function THROWS -- this function THROWS -- this function THROWS 
	function buildEMail ( $params ) {
		
		$keysWanted = array(  // build an intersector
			self::email_substitutions => 1
			,self::email_subject => 1	
			,self::email_body => 1
		);

		// take only what we want from the given params
		$this->data = array_merge(array_intersect_key($params,$keysWanted),$this->data);
		
		// make some standards, if they were not passed in already
		if (empty($this->data[self::email_subject])) { $this->data[self::email_subject] = " "; }
		if (empty($this->data[self::email_body])) { $this->data[self::email_body] = " "; }
		
		// if we have any requested substitutions
		if (!empty($this->data[self::email_substitutions])) {
			
			// loop the list of requested substitutions
			foreach (array_keys($this->data[self::email_substitutions]) as $key) {
				
				// apply each key => value as a substitution to both the body and the subject
				$this->data[self::email_subject] = str_replace($key,$this->data[self::email_substitutions][$key],$this->data[self::email_subject]);
				$this->data[self::email_body] = str_replace($key,$this->data[self::email_substitutions][$key],$this->data[self::email_body]);
			}
		}
		
		return "subj:".self::eol. $this->data[self::email_subject] .self::eol.self::eol."body:".self::eol. $this->data[self::email_body];
	}
	
	// --------------------------------------------------------------------------------------------------------
	// send an email using the given params -- this function THROWS -- this function THROWS -- this function THROWS 
	function sendEMail ( $params ) {

		$keysWanted = array(  // build an intersector
			self::email_to => 1
			,self::email_cc => 1			
			,self::email_bcc => 1
		);

		// take only what we want from the given params
		$this->data = array_merge(array_intersect_key($params,$keysWanted),$this->data);

		// check those things we cannot do without at this time
		if (empty($this->data[self::email_to])) { throw new Exception(" email_to was empty in EMailHandler::sendEMail "); }
		
		// assemble the address lists for the 'to', 'cc', and 'bcc'
		$this->data[self::email_to] = (is_array($this->data[self::email_to]))? join(',',$this->data[self::email_to]): $this->data[self::email_to];
		$this->data[self::email_cc] = (is_array($this->data[self::email_cc]))? join(',',$this->data[self::email_cc]): $this->data[self::email_cc];
		$this->data[self::email_bcc] = (is_array($this->data[self::email_bcc]))? join(',',$this->data[self::email_bcc]): $this->data[self::email_bcc];
		
		// send the email -- we build the header every time we send an email
		$this->data[self::email_result] = mail($this->data[self::email_to], $this->data[self::email_subject], stripslashes($this->data[self::email_body]), $this->buildHeader()); 

		// and... ?
		return $this->data[self::email_result];
	}
	
	// --------------------------------------------------------------------------------------------------------
	// the header must be built independently for each transmission
	function buildHeader () {
		$email_header = "";

		$email_header .= 'User-Agent: PHP/' .phpversion(). self::eol;
		$email_header .= 'From: "' .$this->data[self::email_name]. '" <' .$this->data[self::email_addr].'@'.$this->data[self::email_domain]. '>' .self::eol;  
		$email_header .= 'Return-Path: <' .$this->data[self::email_addr].'@'.$this->data[self::email_domain]. '>' .self::eol;  
		if (! empty($this->data[self::email_cc])) { $email_header .= 'Cc: <' .$this->data[self::email_cc]. '>' .self::eol; }
		if (! empty($this->data[self::email_bcc])) { $email_header .= 'Bcc: <' .$this->data[self::email_bcc]. '>' .self::eol; }
		$email_header .= 'Message-ID: <' .$this->getMessageId(). '%' .$this->data[self::email_domain]. '>' .self::eol;  
		$email_header .= 'Mime-version: 1.0' .self::eol;  
		$email_header .= 'Content-type: text/plain; charset="US-ASCII"' .self::eol; 
		$email_header .= 'Content-transfer-encoding: 7bit' .self::eol;  
		$email_header .= self::eol; 
		$email_header .= self::eol;

		return $email_header;
	}
	
	// --------------------------------------------------------------------------------------------------------
	// email message id's are really a date.time stamp
	function getMessageId () { return date("Ymd.Gis"); }
	
	// --------------------------------------------------------------------------------------------------------
	// the result of the last email that we sent
	function lastResult () { return $this->data[self::email_result]; }
	
};

?>