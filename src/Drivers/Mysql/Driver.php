<?php 
namespace Xdevpusaka\Database\Drivers\Mysql;

use mysqli;
use mysqli_sql_exception;
use Exception;

use Xdevpusaka\Database\Face\DriverInterface;

class Driver implements DriverInterface {

	private $db 	= NULL;
	private $result = NULL;
	private $config	= [];
	private $error 	= [];

	public function __construct($config) {

		mysqli_report(MYSQLI_REPORT_STRICT);

		$this->config = $config;

	}
    
    public function builder() {

    	return new Builder($this);

    }

    public function quotes( $text ) {
    	return '`'.$text.'`';
    }

    function sstring( $string ) {
		return addslashes( $string );
	}
    
    public function open() {

    	if(isset($this->db)) {
			return $this;
		}

		try {

			$this->db = new mysqli(
				$this->config['hostname'] 	?? 'localhost', 
				$this->config['username'] 	?? 'root', 
				$this->config['password'] 	?? '', 
				$this->config['database'] 	?? '',
				$this->config['port'] 		?? '3306'
			);

		}catch(mysqli_sql_exception $e) {

			throw new Exception($e->getMessage());
		
		}


    }
    
    public function close() {

    	// close result
    	if(isset($this->result)) {
    		
    		if(is_bool($this->result)) {
    		}else if($this->result !== NULL) {
    			$this->result->close();
    			unset($this->result);
    		}

    	}

    	// close connection
    	if(isset($this->db)) {

    		try {
				if($this->db !== NULL) {
					$this->db->close();
					unset($this->db);
				}
			}catch(Exception $e) {}

		}

    }
    
    public function execute($query) {

    	$result = $this->db->real_query($query);

		if(!$result) {
			
			$this->error = $this->db->error;

			throw new Exception(json_encode([$this->error]));
		
		}

		return true;

    }
    
    public function query($query) {

    	$this->result = $this->db->query($query, MYSQLI_USE_RESULT);

		if(!$this->result) {
			
			$this->error = $this->db->error;

			throw new Exception(json_encode([$this->error]));
		
		}

		return new Result($this);

    }

    public function result() {
    	return $this->result;
    }
    
    public function transaction() {
    	$this->db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
    }
    
    public function rollback() {
    	$this->db->rollback();
    }
    
    public function commit() {
    	$this->db->commit();
    }
    
    public function error() {
    	return $this->error;
    }

}