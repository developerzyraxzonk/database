<?php 
namespace Xdevpusaka\Database\Drivers\Mysql;

use Exception;
use Closure;

use Xdevpusaka\Database\Blueprint\Column;
use Xdevpusaka\Database\Blueprint\Value;
use Xdevpusaka\Database\Blueprint\Table;

use Xdevpusaka\Database\Face\BuilderInterface;

class Builder implements BuilderInterface {

	private $cache_fetch;
	private $cache_dir;
	private $cache_params;
	private $cache_name;
	private $cache_query;
	
	private $cache_on;
	private $trans_on;
	private $driver;

	private $selects 	= [];
	private $tables 	= [];
	private $wheres 	= [];
	private $orders 	= [];
	private $groups 	= [];
	private $joins 		= [];
	private $unions 	= [];
	private $sets 		= [];

	private $limit 		= NULL;
	private $having 	= NULL;

	private $state 		= NULL;

	private $ignore 	= FALSE;

	/**
	 * Common => __construct 
	 * @return void
	 */
	public function __construct( $driver ) {
		
		$this->cache_fetch 	= false;
		$this->cache_dir 	= ROOTDIR . 'storage/private/caches/query/';
		$this->cache_params = [];
		$this->cache_query 	= '';
		
		$this->cache_on 	= false;
		$this->trans_on 	= false;
		$this->driver 		= $driver;

		$this->ignore 		= FALSE;
	}

	public function __set($key, $value) 
	{
		
		if(in_array($key, ['state'])) {
			$this->{$key} = $value;
		}

	}

	public function debug() {
		$this->driver->debug();
	}

	/**
	 * Common => close 
	 * @return void
	 */
	public function close() {
		$this->driver->close();
	}

	/**
	 * Common => fetch 
	 * @return array
	 */
	public function clear() {

		$this->selects 	= [];
		$this->tables 	= [];
		$this->wheres 	= [];
		$this->orders 	= [];
		$this->groups 	= [];
		$this->joins 	= [];
		$this->unions 	= [];
		$this->sets 	= [];

		$this->limit 	= NULL;
		$this->having 	= NULL;

		$this->cache_on 	= false;
		$this->cache_fetch 	= false;
		$this->cache_params = [];
		$this->cache_query 	= '';

	}

	/**
	 * Common => __destruct 
	 * @return void
	 */
	public function __destruct() {
	}

	public function cache($name, $params) {
		
		$this->cache_name 		= $name;
		$this->cache_params 	= $params;
		$this->cache_on 		= true;

		$this->cache_fetch 		= false;
		
		$hash 	= md5(json_encode($params));

		$cache 	= $this->cache_dir . $this->cache_name . '.cache';

		if(!file_exists($cache)) {
			return;
		}

		$cache 	= json_decode(file_get_contents($cache));

		if($cache === NULL) {
			return;
		}

		if(isset($cache->hash) AND isset($cache->query)) {

			if( $hash === $cache->hash AND $cache->query !== '' ) {
				$this->cache_query = $cache->query;
				$this->cache_fetch = true;
			}

		}

	}

	/**
	 * Transaction => transaction 
	 * @return void
	 */
	public function transaction() {
		$this->trans_on = true;
		$this->driver->open();
		$this->driver->transaction();
	}

	/**
	 * Transaction => commit 
	 * @return void
	 */
	public function commit() {
		$this->driver->commit();
		$this->driver->close();
	}

	/**
	 * Transaction => transaction 
	 * @return void
	 */
	public function rollback() {
		$this->driver->rollback();
		$this->driver->close();
	}

	/**
	 * Query => select 
	 * @return void
	 */
	public function select() {

		if($this->cache_fetch) { return $this; }

		$argc = func_num_args();

		if ($argc == 1) 
		{

			$arg = func_get_arg(0);

			if (is_string($arg)) 
			{
				$this->selects[] = $arg;
			}

			else if (is_array($arg)) 
			{
				foreach ($arg as $param) {
					$this->select($param);
				}
			}

			else if ($arg instanceof Closure) 
			{
				$this->selects[] = $this->subQuery($arg);
			}

			else if ($arg instanceof Value) 
			{
				$this->selects[] = $arg->get();
			}

			return $this;

		}

		else if ($argc > 1)
		{

			foreach (func_get_args() as $param) {
				$this->select($param);
			}

		}

		return $this;

	}

	/**
	 * Query => table 
	 * @return void
	 */
	public function table($alias, $closure = NULL) {

		if($this->cache_fetch) { return $this; }

		// simple table
		//---------------------------------------
		if ($closure === NULL) 
		{

			if( is_string($alias) ) {

				if(preg_match('/(\w+)(\(.*\))/', $alias, $match) > 0) {
					$alias = $this->driver->quotes($match[1]) . $match[2];
				}else {
					$alias = $this->driver->quotes($alias);
				};
				
				$this->tables[] = $alias;

			}

			return $this;

		}

		// temporary table
		//---------------------------------------
		if( !is_string($alias) ) {
			throw new Exception('Builder::table() first argument must be string.');
		}

		if( !($closure instanceof Closure) ) {
			throw new Exception('Builder::table() second argument must be closure.');
		}

		$this->tables[] = $this->subQuery($closure) . ' ' . $alias;

		return $this;

	}

	/** 
	 * ==============================================
	 * WHERE
	 * ==============================================
	 */
	public function where()
	{

		if($this->cache_fetch) { return $this; }

		$argc = func_num_args();

		if ($argc == 1) 
		{

			$arg = func_get_arg(0);

			// raw where
			if (is_string($arg)) {
				
				if(!empty($this->wheres)) {
					$this->wheres[] = ' AND ' . $arg;
				}else {
					$this->wheres[] = $arg;
				}

			}
			else if ($arg instanceof closure) {

				if(!empty($this->wheres)) {
					$this->wheres[] = ' AND ' . $this->subQuery($arg, 'where');
				}else {
					$this->wheres[] = $this->subQuery($arg, 'where');
				}

			}

		}

		else if($argc == 2)
		{

			$column = func_get_arg(0);
			$value 	= func_get_arg(1);

			if($column instanceof Column) {
				$column = $value->name;
			}else if ($column instanceof closure) {
				$column = $this->subQuery($column);
			}

			if($value instanceof Column) {
				$value = $value->name;
			}else if ($value instanceof closure) {
				$value = $this->subQuery($value);
			}else {
				$value = $this->value($value);
			}

			if(!empty($this->wheres)) {
				$this->wheres[] = ' AND ' . $column . '=' . $value;
			}else {
				$this->wheres[] = $column . '=' . $value;
			}

		}

		else if($argc == 3)
		{
			
			$column 	= func_get_arg(0);
			$operator 	= func_get_arg(1);
			$value 		= func_get_arg(2);
			$operator 	= ' ' . $operator . ' ';

			if($column instanceof Column) {
				$column = $value->name;
			}else if ($column instanceof closure) {
				$column = $this->subQuery($column);
			}

			if($value instanceof Column) {
				$value = $value->name;
			}else if ($value instanceof closure) {
				$value = $this->subQuery($value);
			}else {
				$value = $this->value($value);
			}


			if(!empty($this->wheres)) {
				$this->wheres[] = ' AND ' . $column . $operator . $value;
			} else {
				$this->wheres[] = $column . $operator . $value;
			}

		}

		return $this;

	}

	public function whereNot($column, $value) 
	{

		if($this->cache_fetch) { return $this; }
		
		$this->where($column, ' NOT ', $value);

		return $this;
	}
	
	public function whereIn($column, $values)
	{

		if($this->cache_fetch) { return $this; }
		
		$this->where($column, ' IN ', $values);

		return $this;
	
	}
	
	public function whereNotIn($column, $values)
	{

		if($this->cache_fetch) { return $this; }

		$this->where($column, ' NOT IN ', $values);

		return $this;

	}

	public function whereBetween($column, $between)
	{

		if($this->cache_fetch) { return $this; }

		if(!is_array($between)) {
			throw new \Exception("Builder::whereBetween($column, $between) | $between must be an array");
		}

		$this->where($column . ' NOT BETWEEN ' . implode(' AND ', $between));

		return $this;

	}

	public function whereNotBetween($column, $between)
	{

		if($this->cache_fetch) { return $this; }

		if(!is_array($between)) {
			throw new \Exception("Builder::whereNotBetween($column, $between) | $between must be an array");
		}

		$this->where($column . ' NOT BETWEEN ' . implode(' AND ', $between));

		return $this;

	}

	public function whereNull($column)
	{

		if($this->cache_fetch) { return $this; }

		$this->where($column . ' IS NULL ');

		return $this;

	}

	public function whereNotNull($column)
	{

		if($this->cache_fetch) { return $this; }

		$this->where($column . ' IS NOT NULL ');

		return $this;

	}

	public function orWhere()
	{

		if($this->cache_fetch) { return $this; }

		$argc = func_num_args();

		if ($argc == 1) 
		{

			$arg = func_get_arg(0);

			// raw where
			if (is_string($arg)) {
				
				if(!empty($this->wheres)) {
					$this->wheres[] = ' OR ' . $arg;
				}else {
					$this->wheres[] = $arg;
				}

			}
			else if ($arg instanceof closure) {

				if(!empty($this->wheres)) {
					$this->wheres[] = ' OR ' . $this->subQuery($arg, 'where');
				}else {
					$this->wheres[] = $this->subQuery($arg, 'where');
				}

			}

		}

		else if($argc == 2)
		{

			$column = func_get_arg(0);
			$value 	= func_get_arg(1);

			if($value instanceof Column) {
				$value = $value->name;
			}else {
				$value = $this->value($value);
			}

			if(!empty($this->wheres)) {
				$this->wheres[] = ' OR ' . $column . '=' . $value;
			}else {
				$this->wheres[] = $column . '=' . $value;
			}

		}

		else if($argc == 3)
		{
			
			$column 	= func_get_arg(0);
			$operator 	= func_get_arg(1);
			$value 		= func_get_arg(2);

			if($column instanceof closure) {
				$column = $this->subQuery($column);
			}

			if($value instanceof Column) {
				$value = $value->name;
			}else {
				$value = $this->value($value);
			}

			if(!empty($this->wheres)) {
				$this->wheres[] = ' OR ' . $column . $operator . $value;
			}else {
				$this->wheres[] = $column . $operator . $value;
			}

		}

		return $this;

	}
	
	public function orWhereNot($column, $value)
	{

		if($this->cache_fetch) { return $this; }

		$this->orWhere($column, ' NOT ', $value);

		return $this;

	}
	
	public function orWhereIn($column, $values)
	{

		if($this->cache_fetch) { return $this; }

		$this->orWhere($column, ' NOT ', $values);

		return $this;

	}

	public function orWhereNotIn($column, $values)
	{

		if($this->cache_fetch) { return $this; }
		
		$this->orWhere($column, ' NOT IN ', $values);

		return $this;

	}

	public function orWhereBetween($column, $between)
	{

		if($this->cache_fetch) { return $this; }

		if(!is_array($between)) {
			throw new \Exception("Builder::orWhereBetween($column, $between) | $between must be an array");
		}

		$this->orWhere($column . ' NOT BETWEEN ' . implode(' AND ', $between) );

		return $this;

	}

	public function orWhereNotBetween($column, $between)
	{

		if($this->cache_fetch) { return $this; }

		if(!is_array($between)) {
			throw new \Exception("Builder::orWhereNotBetween($column, $between) | $between must be an array");
		}

		$this->orWhere($column . ' NOT BETWEEN ' . implode(' AND ', $between) );

		return $this;

	}
	
	public function orWhereNull($column)
	{

		if($this->cache_fetch) { return $this; }

		$this->orWhere($column . ' IS NULL ');

		return $this;

	}

	public function orWhereNotNull($column)
	{

		if($this->cache_fetch) { return $this; }

		$this->orWhere($column . ' IS NOT NULL ');

		return $this;

	}

	/** 
	 * ==============================================
	 * GROUP, ORDER, LIMIT, HAVING
	 * ==============================================
	 */
	public function orderBy($column, $order) {

		if($this->cache_fetch) { return $this; }

		$this->orders[] = $column . ' ' . strtoupper($order);

		return $this;

	}

	public function groupBy() {

		if($this->cache_fetch) { return $this; }

		$args = func_get_args();

		$this->groups[] = implode(',', $args);

		return $this;

	}

	public function limit($start, $length) {

		if($this->cache_fetch) { return $this; }

		if(!is_int($start)) {
			throw new \Exception("Builder::limit($start, $length) | $start must be an Integer");
		}

		if(!is_int($length)) {
			throw new \Exception("Builder::limit($start, $length) | $length must be an Integer");
		}

		$this->limit = "$start, $length";

		return $this;

	}

	public function having()
	{

		if($this->cache_fetch) { return $this; }

		$argc = func_num_args();

		if ($argc == 1) 
		{

			$arg = func_get_arg(0);

			// raw having
			if (is_string($arg)) {
				
				$this->having = $arg;

			}

		}

		return $this;

	}

	/** 
	 * ==============================================
	 * JOINS, UNION
	 * ==============================================
	 */
	public function join() 
	{

		if($this->cache_fetch) { return $this; }

		$prefix 	= '';
		$command 	= '';
		$argc 		= func_num_args();

		if ($argc == 2) 
		{

			//JOIN table2 ON table1.a = table2.b

			$table = func_get_arg(0);
			$join  = func_get_arg(1);
			$on    = '';

			if(is_string($table)) {
				$command = $prefix . 'JOIN '.$table. ' ';
			}

			if($join instanceof closure) {

				$builder = new Builder($this->driver);

				$join($builder);

				$command .= $builder->getQuery();

			}

			$this->joins[] = $command;

		}

		return $this;

	}

	public function joinLeft() {

		if($this->cache_fetch) { return $this; }

		$prefix 	= 'LEFT ';
		$command 	= '';
		$argc 		= func_num_args();

		if ($argc == 2) 
		{

			//JOIN table2 ON table1.a = table2.b

			$table = func_get_arg(0);
			$join  = func_get_arg(1);
			$on    = '';

			if(is_string($table)) {
				$command = $prefix . 'JOIN '.$table. ' ';
			}

			if($join instanceof closure) {

				$builder = new Builder($this->driver);

				$join($builder);

				$command .= $builder->getQuery();

			}

			$this->joins[] = $command;

		}

		return $this;

	}
	
	public function joinRight() {

		if($this->cache_fetch) { return $this; }

		$prefix 	= 'RIGHT ';
		$command 	= '';
		$argc 		= func_num_args();

		if ($argc == 2) 
		{

			//JOIN table2 ON table1.a = table2.b

			$table = func_get_arg(0);
			$join  = func_get_arg(1);
			$on    = '';

			if(is_string($table)) {
				$command = $prefix . 'JOIN '.$table. ' ';
			}

			if($join instanceof closure) {

				$builder = new Builder($this->driver);

				$join($builder);

				$command .= $builder->getQuery();

			}

			$this->joins[] = $command;

		}

		return $this;

	}
	
	public function joinFull() {

		if($this->cache_fetch) { return $this; }

		$prefix 	= '';
		$command 	= '';
		$argc 		= func_num_args();

		if ($argc == 1) 
		{

			//JOIN table2 ON table1.a = table2.b

			$table = func_get_arg(0);
			
			$this->tables[] = $table;

		}

		return $this;

	}

	public function on($col1, $operator, $col2) 
	{

		if($this->cache_fetch) { return $this; }

		if(empty($this->joins)) {
			$this->joins[] = ' ON ' . $col1 . ' ' . $operator . ' ' . $col2;
		}else {
			$this->joins[] = ' AND ' . $col1 . ' ' . $operator . ' ' . $col2;
		}

		return $this;

	}

	public function orOn($col1, $operator, $col2) 
	{

		if($this->cache_fetch) { return $this; }

		if(empty($this->joins)) {
			$this->joins[] = ' ON ' . $col1 . ' ' . $operator . ' ' . $col2;
		}else {
			$this->joins[] = ' OR ' . $col1 . ' ' . $operator . ' ' . $col2;
		}

		return $this;

	}

	public function union($query) 
	{

		if($this->cache_fetch) { return $this; }

		if(empty($this->unions)) {
			$this->unions[] = $this->subQuery($query);
		}else {
			$this->unions[] = ' UNION ' . $this->subQuery($query);
		}

		return $this;

	}

	public function unionAll($query) 
	{

		if($this->cache_fetch) { return $this; }

		if(empty($this->unions)) {
			$this->unions[] = $this->subQuery($query);
		}else {
			$this->unions[] = ' UNION ALL ' . $this->subQuery($query);
		}

		return $this;

	}

	/** 
	 * ==============================================
	 * ALIAS
	 * ==============================================
	 */
	public function alias($as, $col)
	{

		if(!is_string($as)) 
		{
			throw new \Exception('Builder::alias($as, $col) | $as parameter must be string');
		}

		if(is_string($col)) 
		{
			return $this->value($col) . ' AS ' . $as;
		}

		else if($col instanceof closure) 
		{
			return $this->subQuery($col) . ' AS ' .$as;
		}

		elseif ($col instanceof Column) {
			return $col->name . ' AS ' .$as;
		}

		return NULL;

	}

	public function uuid() 
	{
		return "REPLACE(UPPER(UUID()), '-', '')";
	}

	public function value($value) 
	{

		if(is_string($value)) {
			return "'". $this->driver->sstring($value) . "'";
		}

		if(is_numeric($value)) {
			return $value;
		}

		if(is_array($value)) {

			$in = [];

			foreach ($value as $val) {
				$in[] = $this->value($val);
			}

			return '('.implode(',', $in).')';

		}

		if($value instanceof Closure) {

			return $this->subQuery($value);

		}

		if($value instanceof Column) {

			$column = $value->name;
			unset($value);

			return $column;

		}

		return 'null';

	}

	/**
	 * Build => funct 
	 * @return string
	 */
	public function funct($funct, $sub)
	{

		if($sub instanceof closure) {
			return strtr( $funct, [ '<<sub>>' => $this->subQuery($sub) ] );
		}else {
			return strtr( $funct, [ '<<sub>>' => $sub ] );
		}

		return NULL;

	}


	/**
	 * Build => subQuery 
	 * @return string
	 */
	public function subQuery($closure, $state = NULL) {

		if($this->cache_fetch) { return $this; }

		$query 			= new Builder($this->driver);

		$query->state 	= $state;

		$closure($query);

		$sub = '( ' . $query->getQuery() . ' )';

		$query->clear();

		return $sub;

	}

	/**
	 * Build => getQuery 
	 * @return string
	 */
	public function getQuery() {

		// get from cache
		if($this->cache_on AND $this->cache_fetch) {
			$query = $this->cache_query;
			$this->clear();
			return $query;
		}

		$unions  = $this->unions;
		$selects = $this->selects;
		$tables  = $this->tables;
		$joins   = $this->joins;
		$wheres  = $this->wheres;
		$groups  = $this->groups;
		$having  = $this->having;
		$orders	 = $this->orders;
		$limit 	 = $this->limit;

		$count_union 	= count($unions);
		$count_select 	= count($selects);
		$count_table 	= count($tables);
		$count_join 	= count($joins);
		$count_where 	= count($wheres); 
		$count_group 	= count($groups);
		$count_order 	= count($orders);

		if($count_select <= 0) {
			$selects[] = '*';
		}

		$union 	 = implode(' ', $unions);
		$select  = implode(',', $selects);
		$table 	 = implode(',', $tables);
		$join 	 = implode(' ', $joins);
		$where 	 = implode(' ', $wheres);
		$order 	 = implode(',', $orders);
		$group 	 = implode(',', $groups);

		$query 	 = '';

		if ($count_select > 0) {
			$query 	.= 'SELECT ' . $select; 
		}

		if ($count_table  > 0) {
			$query .= ' FROM '.$table;
		}

		if ($count_join > 0) {
			$query .= ' ' . $join;
		}

		if ($count_where  > 0) {

			if($this->state == NULL) {
				
				$query .= ' WHERE '. $where;
			
			}else if($this->state == 'where') {
			
				$query .= $where;
			
			}

		}

		if ($count_group > 0) {
			$query .= ' GROUP BY ' . $group;
		}

		if (!is_null($having)) {
			$query .= ' HAVING ' . $having;
		}

		if ($count_order > 0) {
			$query .= ' ORDER BY ' . $order;
		}

		if (!is_null($limit)) {
			$query .= ' LIMIT ' . $limit;
		}

		if ($count_union > 0) {
			$query 	= $union; 
		}

		if($this->cache_on) {

			$data = json_encode([
				'query' => $query,
				'hash'	=> md5(json_encode($this->cache_params))
			]);

			file_put_contents($this->cache_dir . $this->cache_name . '.cache', $data);
		}

		$this->clear();

		return $query;

	}

	/**
	 * Result => get 
	 * @return array
	 */
	public function get() {

		$trans_on 	= $this->trans_on;

		$query 		= $this->getQuery();

		if(!$trans_on) { $this->driver->open(); }

		$result = $this->driver->query($query);

		$rows	= $result->all();

		if(!$trans_on) { $this->driver->close(); }

		return $rows;

	}

	/**
	 * Result => first 
	 * @return stdClass
	 */
	public function first() {

		$trans_on 	= $this->trans_on;

		$query 		= $this->getQuery();

		if(!$trans_on) { $this->driver->open(); }

		$result = $this->driver->query($query);

		$rows	= $result->first();

		if(!$trans_on) { $this->driver->close(); }

		return $rows;

	}

	/**
	 * Result => fetch 
	 * @return array
	 */
	public function fetch($todo) {

		$trans_on 	= $this->trans_on;

		$query 		= $this->getQuery();

		if(!$trans_on) { $this->driver->open(); }

		$result = $this->driver->query($query);

		$rows	= $result->fetch($todo);

		if(!$trans_on) { $this->driver->close(); }

		return $rows;

	}

	/**
	 * ==================================================================
	 * @method insertQuery
	 * @return string
	 * ==================================================================
	 */
	public function insertQuery($records) {

		$column = [];
		$values = [];
		$table  = $this->tables[0] ?? NULL;
		
		if($this->ignore) {	
			$query  = 'INSERT IGNORE INTO ';
		}else {
			$query  = 'INSERT INTO ';
		}

		if(is_null($table)) {
			throw new Exception("Builder::insert(\$records) | table cannot be empty.");
		}

		$query 		.= $table;

		$column_def  = '';
		$values_def  = '';

		if(is_array($records)) {

			// sigle record
			if(array_keys($records) !== range(0, count($records) - 1)) {
				
				// set to multiple records
				$records = [
					$records
				];

			}

			$init 	= true;

			// multiple records
			foreach ($records as $i => $record) {
				
				$value 	= [];
				
				foreach ($record as $col => $val) {
				
					if($init) {
						$column[] = $col;
					}

					$value[]  = $this->value($val);
				}
				
				$values[] 	= '( ' . implode(',', $value) . ' )';
				$init 		= false;
				unset($value);
			}

			$column_def = '( '. implode(',', $column) . ' )';
			$values_def = ' VALUES ' . implode(',', $values);

		}

		else if ($records instanceof closure) {

			$values_def = trim($this->subQuery($records), '()');

		}

		$query .= $column_def;
		$query .= $values_def;

		unset($records);
		unset($table);
		unset($column);
		unset($values);

		$this->clear();

		return $query;

	}

	public function insert($records) {

		$query = $this->insertQuery($records);

		if(!$this->trans_on) { $this->driver->open(); }

		$result = $this->driver->execute($query);
		
		if(!$this->trans_on) { $this->driver->close(); }

		return $result;

	}

	public function insertIgnore($records) {

		$this->ignore = true;

		$query 		  = $this->insertQuery($records);

		if(!$this->trans_on) { $this->driver->open(); }

		$result 	  = $this->driver->execute($query);
		
		if(!$this->trans_on) { $this->driver->close(); }

		return $result;

	}

	/**
	 * ==================================================================
	 * @method updateQuery
	 * @return string
	 * ==================================================================
	 */
	public function updateQuery($records, $on = NULL) {

		// UPDATE table SET d=3, e=5 WHERE id=10

		$column = [];
		$values = [];
		$table  = $this->tables[0] ?? NULL;
		$query  = 'UPDATE ';
		$where 	= '';

		if(is_null($table)) {
			throw new \Exception('Builder::update($record) | table cannot be empty.');
		}

		$query 		.= $table;

		$column_def  = '';
		$values_def  = '';

		if(empty($this->wheres)) {
			throw new Exception('Builder::update($record) | where clause cannot be empty.');
		}

		$where 		 = implode(' ', $this->wheres);

		if(is_array($records)) {

			// sigle record
			if(array_keys($records) !== range(0, count($records) - 1)) {
				
				$query .= ' SET ';

				foreach ($records as $column => $value) {

					$values[] = $column . '=' . $this->value($value);

				}

				$values_def = implode(',', $values);

			}else {

				if(is_null($on)) {
					throw new Exception('Builder::updateQuery($records, $on) | $on cannot be NULL');
				}

				if(empty($this->sets)) {
					throw new Exception('Batch update need to set. need [ Builder::set() ]');	
				}

				$temp_table = 'temp_table_join_update';

				$subQuery 	= new Builder($this->driver);

				foreach ($records as $i => $record) {

					$subQuery->unionAll(function($subQuery) use ($record) {

						foreach ($record as $col => $val) {
						
							$subQuery->select($this->value($val) . ' AS ' . $col);
							
						}

					});

				}

				$query .= 'JOIN ( ' . $subQuery->getQuery() . ')' . $temp_table . ' ON ' . $on;

				$query .= implode(',', $this->sets);

				$query 	= strtr($query, ['{join}' => $temp_table]);

			}

		}

		else if ($records instanceof closure) {

			$values_def = trim($this->subQuery($records), '()');

		}

		$query .= $column_def;
		$query .= $values_def;
		$query .= ' WHERE '. $where;

		unset($records);
		unset($table);
		unset($column);
		unset($values);

		$this->clear();

		return $query;

	}

	public function update($records, $on = NULL) {

		$query = $this->updateQuery($records, $on);
		
		if(!$this->trans_on) { $this->driver->open(); }

		$result = $this->driver->execute($query);

		if(!$this->trans_on) { $this->driver->close(); }

		return $result;

	}

	/**
	 * ==================================================================
	 * @method deleteQuery
	 * @return string
	 * ==================================================================
	 */
	public function deleteQuery()
	{

		$table  = $this->tables[0] ?? NULL;
		$query  = 'DELETE ';
		$where 	= '';

		if(is_null($table)) {
			throw new Exception('Builder::delete($record) | table cannot be empty.');
		}

		if(empty($this->wheres)) {
			throw new Exception('Builder::delete($record) | where clause cannot be empty.');
		}

		$where 		 = implode(' ', $this->wheres);

		$query 		.= ' FROM '. $table;
		$query 		.= ' WHERE '. $where;

		$this->clear();

		return $query;

	}

	public function delete()
	{

		$query = $this->deleteQuery();

		if(!$this->trans_on) { $this->driver->open(); }

		$result = $this->driver->execute($query);

		if(!$this->trans_on) { $this->driver->close(); }

		return $result;

	}

	public function set($column, $value) 
	{

		if(empty($this->sets)) {
			$this->sets[] = ' SET ' . $column . ' = ' . $this->value($value);
		}else {
			$this->sets[] = $column . ' = ' . $this->value($value);
		}

		return $this;

	}


}