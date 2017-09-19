<?php
namespace Kir\MySQL\Builder\Traits;

use Kir\MySQL\Builder\Expr\OptionalExpression;
use Kir\MySQL\Builder\Internal\ConditionBuilder;

trait HavingBuilder {
	use AbstractDB;

	/** @var array */
	private $having = array();

	/**
	 * @param string|array $expression
	 * @param mixed ...$_
	 * @return $this
	 */
	public function having($expression, $_ = null) {
		if($expression instanceof OptionalExpression) {
			if($expression->isValid()) {
				$this->having[] = [$expression->getExpression(), $expression->getValue()];
			}
		} else {
			$this->having[] = [$expression, array_slice(func_get_args(), 1)];
		}
		return $this;
	}

	/**
	 * @param string $query
	 * @return string
	 */
	protected function buildHavingConditions($query) {
		return ConditionBuilder::build($this->db(), $query, $this->having, 'HAVING');
	}
}
