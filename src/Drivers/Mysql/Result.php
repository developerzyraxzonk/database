<?php 
namespace Xdevpusaka\Database\Drivers\Mysql;

use Xdevpusaka\Database\Face\ResultInterface;

class Result implements ResultInterface {

	private $driver;

	public function __construct($driver) {
		$this->driver = $driver;
	}

	public function count() {

		if(!$this->driver->result()) {
			return 0;
		}

		return $this->driver->result()->num_rows;

	}
	
	public function all() {

		$rows = [];

		if(!$this->driver->result()) {
			return [];
		}

		while($row = $this->driver->result()->fetch_object()) {
			$rows[] = $row;
			unset($row);
		}

		return $rows;

	}
	
	public function first() {

		$rows = [];

		if(!$this->driver->result()) {
			return [];
		}

		while($row = $this->driver->result()->fetch_object()) {
			$rows[] = $row;
			unset($row);
			break;
		}

		return isset($rows[0]) ? $rows[0] : NULL;

	}
	
	public function last() {

		$rows = [];

		if(!$this->driver->result()) {
			return [];
		}

		while($row = $this->driver->result()->fetch_object()) {
			$rows[0] = $row;
			unset($row);
			break;
		}

		return isset($rows[0]) ? $rows[0] : NULL;

	}

	public function fetch( $todo ) {

		$rows = [];

		if(!$this->driver->result()) {
			return [];
		}

		while($row = $this->driver->result()->fetch_object()) {
			
			$break 	= $todo($row);
			$rows[] = $row;
			
			if($break) {
				break;
			}

		}

		return $rows;

	}

}