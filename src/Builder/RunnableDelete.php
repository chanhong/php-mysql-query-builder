<?php
namespace Kir\MySQL\Builder;

use Kir\MySQL\Builder\Internal\DDLPreparable;
use Kir\MySQL\Builder\Internal\DDLRunnable;
use Kir\MySQL\Builder\Traits\CreateDDLRunnable;

class RunnableDelete extends Delete implements DDLPreparable {
	use CreateDDLRunnable;

	/**
	 * @param array $params
	 * @return int
	 */
	public function run(array $params = []) {
		return $this->prepare()->run($params);
	}

	/**
	 * @return DDLRunnable
	 */
	public function prepare() {
		return $this->createPreparable($this->db()->prepare($this));
	}
}
