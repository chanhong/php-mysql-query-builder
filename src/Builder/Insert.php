<?php
namespace Kir\MySQL\Builder;

use Kir\MySQL\Tools\AliasReplacer;
use RuntimeException;
use UnexpectedValueException;

class Insert extends InsertUpdateStatement {
	/** @var array */
	private $fields = [];
	/** @var array */
	private $update = [];
	/** @var string */
	private $table;
	/** @var string */
	private $keyField;
	/** @var bool */
	private $ignore = false;
	/** @var Select */
	private $from;

	/**
	 * @param string $table
	 * @return $this
	 */
	public function into(string $table) {
		$this->table = $table;
		return $this;
	}

	/**
	 * @param bool $value
	 * @return $this
	 */
	public function setIgnore(bool $value = true) {
		$this->ignore = $value;
		return $this;
	}

	/**
	 * Legt den Primaerschluessel fest.
	 * Wenn bei einem Insert der Primaerschluessel mitgegeben wird, dann wird dieser statt der LastInsertId zurueckgegeben
	 *
	 * @param string $field
	 * @return $this
	 */
	public function setKey(string $field) {
		$this->keyField = $field;
		return $this;
	}

	/**
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @return $this
	 */
	public function add(string $field, $value) {
		$this->fields = $this->addTo($this->fields, $field, $value);
		return $this;
	}

	/**
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @return $this
	 */
	public function update(string $field, $value) {
		$this->update = $this->addTo($this->update, $field, $value);
		return $this;
	}

	/**
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @return $this
	 */
	public function addOrUpdate(string $field, $value) {
		$this->add($field, $value);
		$this->update($field, $value);
		return $this;
	}

	/**
	 * @param string $str
	 * @param mixed ...$args
	 * @return $this
	 */
	public function addExpr(string $str, ...$args) {
		if(count($args) > 0) {
			$this->fields[] = func_get_args();
		} else {
			$this->fields[] = $str;
		}
		return $this;
	}

	/**
	 * @param string $str
	 * @param mixed ...$args
	 * @return $this
	 */
	public function updateExpr(string $str, ...$args) {
		if(count($args) > 0) {
			$this->update[] = func_get_args();
		} else {
			$this->update[] = $str;
		}
		return $this;
	}

	/**
	 * @param string $expr
	 * @param mixed ...$args
	 * @return $this
	 */
	public function addOrUpdateExpr(string $expr, ...$args) {
		if(count($args) > 0) {
			$this->fields[] = func_get_args();
			$this->update[] = func_get_args();
		} else {
			$this->fields[] = $expr;
			$this->update[] = $expr;
		}
		return $this;
	}

	/**
	 * @param array $data
	 * @param array|null $mask
	 * @param array|null $excludeFields
	 * @return $this
	 */
	public function addAll(array $data, array $mask = null, array $excludeFields = null) {
		$this->addAllTo($data, $mask, $excludeFields, function ($field, $value) {
			$this->add($field, $value);
		});
		return $this;
	}

	/**
	 * @param array $data
	 * @param array|null $mask
	 * @param array|null $excludeFields
	 * @return $this
	 */
	public function updateAll(array $data, array $mask = null, array $excludeFields = null) {
		$this->addAllTo($data, $mask, $excludeFields, function ($field, $value) {
			if ($field !== $this->keyField) {
				$this->update($field, $value);
			}
		});
		return $this;
	}

	/**
	 * @param array $data
	 * @param array|null $mask
	 * @param array|null $excludeFields
	 * @return $this
	 */
	public function addOrUpdateAll(array $data, array $mask = null, array $excludeFields = null) {
		$this->addAll($data, $mask, $excludeFields);
		$this->updateAll($data, $mask, $excludeFields);
		return $this;
	}

	/**
	 * @param Select $select
	 * @return $this
	 */
	public function from(Select $select) {
		$this->from = $select;
		return $this;
	}

	/**
	 * @return string
	 */
	public function __toString(): string {
		if ($this->table === null) {
			throw new RuntimeException('Specify a table-name');
		}

		$tableName = (new AliasReplacer($this->db()->getAliasRegistry()))->replace($this->table);

		$queryArr = [];
		$ignoreStr = $this->ignore ? ' IGNORE' : '';
		$queryArr[] = "INSERT{$ignoreStr} INTO\n\t{$tableName}\n";

		if($this->from !== null) {
			$fields = $this->from->getFields();
			$queryArr[] = sprintf("\t(%s)\n", implode(', ', array_keys($fields)));
			$queryArr[] = $this->from;
		} else {
			$fields = $this->fields;
			$insertData = $this->buildFieldList($fields);
			if (!count($insertData)) {
				throw new RuntimeException('No field-data found');
			}
			$queryArr[] = sprintf("SET\n%s\n", implode(",\n", $insertData));
		}

		$updateData = $this->buildUpdate();
		if($updateData) {
			$queryArr[] = "{$updateData}\n";
		}

		return implode('', $queryArr);
	}

	/**
	 * @param array $fields
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @return array
	 */
	private function addTo(array $fields, string $field, $value) {
		if ($this->isFieldNameValid($field)) {
			throw new UnexpectedValueException('Field name is invalid');
		}
		$sqlField = $field;
		$sqlValue = $this->db()->quote($value);
		$fields[$sqlField] = $sqlValue;
		return $fields;
	}

	/**
	 * @param array $data
	 * @param array|null $mask
	 * @param array|null $excludeFields
	 * @param callable $fn
	 * @return $this
	 */
	private function addAllTo(array $data, ?array $mask = null, ?array $excludeFields = null, $fn = null) {
		if($mask !== null) {
			$data = array_intersect_key($data, array_combine($mask, $mask));
		}
		if($excludeFields !== null) {
			foreach($excludeFields as $excludeField) {
				if(array_key_exists($excludeField, $data)) {
					unset($data[$excludeField]);
				}
			}
		}
		$data = $this->clearValues($data);
		foreach ($data as $field => $value) {
			$fn($field, $value);
		}
		return $this;
	}

	/**
	 * @return string
	 */
	private function buildUpdate(): string {
		$queryArr = [];
		if(!empty($this->update)) {
			$queryArr[] = "ON DUPLICATE KEY UPDATE\n";
			$updateArr = [];
			if($this->keyField !== null) {
				$updateArr[] = "\t`{$this->keyField}` = LAST_INSERT_ID({$this->keyField})";
			}
			$updateArr = $this->buildFieldList($this->update, $updateArr);

			$queryArr[] = implode(",\n", $updateArr);
		}
		return implode('', $queryArr);
	}

	/**
	 * @param string $fieldName
	 * @return bool
	 */
	private function isFieldNameValid(string $fieldName): bool {
		return is_numeric($fieldName) || !is_scalar($fieldName);
	}

	/**
	 * @param array $values
	 * @return array
	 */
	private function clearValues(array $values): array {
		if(!count($values)) {
			return [];
		}

		$tableName = (new AliasReplacer($this->db()->getAliasRegistry()))->replace($this->table);
		$fields = $this->db()->getTableFields($tableName);
		$result = [];

		foreach ($values as $fieldName => $fieldValue) {
			if(in_array($fieldName, $fields)) {
				$result[$fieldName] = $fieldValue;
			}
		}

		return $result;
	}
}
