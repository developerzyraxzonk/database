<?php 
namespace Xdevpusaka\Database\Blueprint;

class Column {

	/** 
	 * string=varchar
	 * char=char 
	 * text=text
	 * int=integer
	 * decimal=decimal
	 * date
	 * time
	 * datetime
	 * timestamp
	 */

	private $type; 
	private $name;
	private $length;
	private $null; // true | false
	private $key; // uniqe, primary, NULL
	private $default;

	function __construct($name = NULL) {
		$this->name = $name;
	}

	function __set($prop, $value) {
		$this->{$prop} = $value;
	}

	function __get($prop) {
		return $this->{$prop};
	}

}