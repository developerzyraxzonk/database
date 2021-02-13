<?php 
namespace Xdevpusaka\Database;

class Manager {

	static function on( $name ) {

		$configs 	= config('xdevpusaka.databases');

		$config 	= $configs[$name] ?? NULL;

		if ($config === NULL) {
			
			throw new \Exception("Configuration database[$name] not found");
			return NULL;

		}

		if ($config['driver'] == 'mysql') {
			return new \Xdevpusaka\Database\Drivers\Mysql\Driver( $config );
		}

		return NULL;

	}


}