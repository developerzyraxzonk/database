<?php 
namespace Xdevpusaka\Database\Blueprint;

class Table {

	private $name;
	private $columns = [];

	function __construct($name = NULL) {
		$this->name = $name;
	}

	function __set($prop, $value) {
		$this->{$prop} = $value;
	}

	function __get($prop) {
		return $this->{$prop};
	}

	function addColumn( $column ) {

		$this->columns[$column->name] = $column;

	}

}