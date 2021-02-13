<?php 
namespace Xdevpusaka\Database\Face;

interface BuilderInterface {

	public function __construct($driver);
	public function close();
	public function __destruct();

	public function transaction();
	public function commit();
	public function rollback();

	public function select();
	public function table($alias, $closure);

	public function where();
	public function whereNot($column, $value);
	public function whereIn($column, $values);
	public function whereNotIn($column, $values);
	public function whereBetween($column, $between);
	public function whereNotBetween($column, $between);
	public function whereNull($column);
	public function whereNotNull($column);

	public function orWhere();
	public function orWhereNot($column, $value);
	public function orWhereIn($column, $values);
	public function orWhereNotIn($column, $values);
	public function orWhereBetween($column, $between);
	public function orWhereNotBetween($column, $between);
	public function orWhereNull($column);
	public function orWhereNotNull($column);

	public function orderBy($column, $order);
	public function groupBy();
	public function limit($start, $length);
	public function having();

	public function alias($as, $col);

	public function join();
	public function joinLeft();
	public function joinRight();
	public function joinFull();

	public function on($col1, $operator, $col2);
	public function orOn($col1, $operator, $col2);

	public function union($query);
	public function unionAll($query);

	public function set($column, $value);

	public function insertQuery($records);
	public function insert($records);

	public function updateQuery($records, $on);
	public function update($records, $on);

	public function deleteQuery();
	public function delete();	

	public function get();
	public function first();
	public function fetch($todo);

}