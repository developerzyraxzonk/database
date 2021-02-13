<?php 
namespace Xdevpusaka\Database\Face;

interface DriverInterface {

	public function __construct($config);
    public function builder();
    public function quotes($text);
    public function open();
    public function close();
    public function execute($query);
    public function query($query);
    public function transaction();
    public function rollback();
    public function commit();
    public function error();

}