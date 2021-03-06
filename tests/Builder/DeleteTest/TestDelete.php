<?php
namespace Kir\MySQL\Builder\DeleteTest;

use Kir\MySQL\Builder\Delete;
use Kir\MySQL\Databases\TestDB;

class TestDelete extends Delete {
	/**
	 * @return static
	 */
	public static function create() {
		$functions = array(
			'getTableFields' => function ($tableName) {
				return array(
					'id',
					'name',
					'last_update'
				);
			}
		);
		$db = new TestDB($functions);
		return new static($db);
	}

	/**
	 * @return string
	 */
	public function asString() {
		return $this->__toString();
	}
}