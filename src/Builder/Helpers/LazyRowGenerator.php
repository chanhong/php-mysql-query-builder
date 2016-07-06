<?php
namespace Kir\MySQL\Builder\Helpers;

use Closure;
use Kir\MySQL\Builder\QueryStatement;

class LazyRowGenerator {
	/** @var bool */
	private $preserveTypes;

	/**
	 * @param bool $preserveTypes
	 */
	public function __construct($preserveTypes) {
		$this->preserveTypes = $preserveTypes;
	}

	/**
	 * @param QueryStatement $statement
	 * @param Closure $callback
	 * @return array[]|\Generator
	 */
	public function generate(QueryStatement $statement, Closure $callback = null) {
		while($row = $statement->fetch()) {
			if($this->preserveTypes) {
				$columnDefinitions = FieldTypeProvider::getFieldTypes($statement);
				$row = FieldValueConverter::convertValues($row, $columnDefinitions);
			}
			if($callback !== null) {
				$result = $callback($row);
				if($result instanceof DBIgnoreRow) {
					// Do nothing in this case
				} elseif($result !== null) {
					yield $result;
				} else {
					yield $row;
				}
			} else {
				yield $row;
			}
		}
		$statement->closeCursor();
	}
}
