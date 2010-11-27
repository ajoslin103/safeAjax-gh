<?php

//	Cassandra CF_Index - built to help deal with single-key Cassandra Indexes
//
//	changelog, see allen @joslin .net for changes
//
//		09/07/10 - version 1


class CF_Index {
	
	var $_index; // cassandra object
	var $_initOK; 
	
	var $_keyspace;
	var $_cfName;
	
	var $_remoteKeyName; // the 'value' of the index key
	
	var $isReady;
	var $lastErr;
	
	function getCF () { return $this->_index; }
	
	function setReady ( $newValue )	{ $cache = $this->isReady; $this->isReady = $newValue; return $cache; }
	function isReady () { return $this->setReady(true); }  // retrieving the error clears the error
	
	function setLastError ( $newValue ) { $cache = $this->lastErr; $this->lastErr = $newValue; return $cache; }
	function lastError () { return $this->setLastError(""); }  // retrieving the error clears the error
	
	// ----------------------------------------------------
	function __construct ( $ksp, $cfn, $rkn ) { 
		$this->isReady = true; $this->lastErr = "";
		$this->_remoteKeyName = $rkn; $this->_keyspace = $ksp; $this->_cfName = $cfn; 
		$this->_index = new CassandraCF($ksp,$cfn); $this->_initOK = ($this->getCF() != null);
		if (! $this->_initOK) { $this->setReady(false); $this->setLastError("Index did not initialize"); }
	}
	
	// ----------------------------------------------------
	function __destruct () { }
		
	// ----------------------------------------------------
	// pull the remote key 'value' from the index, else return null
	function getRemoteKey ( $key ) {
		try {
			if (! $this->_initOK) { throw new Exception("index not ready in ".__FILE__." at line ". __LINE__); }
			
			$row = $this->getCF()->get($key); // ask for it
			
			if (empty($row)) {
				return null; // not found = no error
			}

			if (! array_key_exists($this->_remoteKeyName,$row)) { throw new Exception("internal error: ".$this->_remoteKeyName." is missing in: ".$this->_cfName); } 
			if (empty($row[$this->_remoteKeyName])) { throw new Exception("internal error: ".$this->_remoteKeyName." is empty in: ".$this->_cfName); } 
			
			return $row[$this->_remoteKeyName];
		}
		
		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}
		
		return null;
	}
	
	// ----------------------------------------------------
	// insert a new 'key' & 'value' into the index, delete the old row by 'key' if exists
	function insertIndexRow ( $newKey, $newValue, $oldKey=null ) { 
		try {
			if (! $this->_initOK) { throw new Exception("index not ready in ".__FILE__." at line ". __LINE__); }
		
			$this->getCF()->insert($newKey, array( $this->_remoteKeyName => $newValue ));
			
			$this->deleteIndexRow($oldKey);
		}
		
		catch (Exception $ex){
			$this->setReady(false);
			$this->setLastError($ex->getMessage());
		}
		
		return null;
	}
	
	// ----------------------------------------------------
	function deleteIndexRow ( $oldKey ) { 
		// delete the old index row by 'key' if exists
		if (! $this->_initOK) { throw new Exception("index not ready in ".__FILE__." at line ". __LINE__); }
	
		if ($oldKey) {
			try { $this->getCF()->remove($oldKey); }
			catch (Exception $ignored) { } // s'ok if it wasn't there already
		}
	
		return null;
	}
};

?>