<?php 
namespace Xdevpusaka\Database\Face;

interface ResultInterface {

	public function __construct($driver);
	public function count();
	public function all();
	public function first();
	public function last();
	public function fetch($todo);

}