<?php
namespace Kir\MySQL\Builder\Traits;

trait TableBuilder {
	use AbstractTableNameBuilder;

	/** @var array[] */
	private $tables = [];

	/**
	 * @param string $alias
	 * @param string $table
	 * @return $this
	 */
	protected function addTable($alias, $table = null) {
		if($table === null) {
			list($alias, $table) = [$table, $alias];
		}
		$this->tables[] = ['alias' => $alias, 'name' => $table];
		return $this;
	}

	/**
	 * @param string $query
	 * @return string
	 */
	protected function buildTables($query) {
		$arr = [];
		foreach($this->tables as $table) {
			$arr[] = "\t".$this->buildTableName($table['alias'], $table['name']);
		}
		if(count($arr)) {
			$query .= implode(",\n", $arr)."\n";
		}
		return $query;
	}

	/**
	 * @return array[]
	 */
	protected function getTables() {
		return $this->tables;
	}
}
