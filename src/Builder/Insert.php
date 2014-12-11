<?php
namespace Kir\MySQL\Builder;

use Kir\MySQL\Tools\AliasReplacer;
use UnexpectedValueException;

class Insert extends InsertUpdateStatement {
	/**
	 * @var array
	 */
	private $fields = array();
	/**
	 * @var array
	 */
	private $update = array();
	/**
	 * @var string
	 */
	private $table = null;
	/**
	 * @var string
	 */
	private $keyField = null;
	/**
	 * @var bool
	 */
	private $ignore = false;
	/**
	 * @var Select
	 */
	private $from = null;
	/**
	 * @var callable
	 */
	private $tableFields = null;

	/**
	 * @param string $table
	 * @return $this
	 */
	public function into($table) {
		$this->table = $table;
		$this->tableFields = function () {
			static $cache = null;
			if($cache === null) {
				$cache = $this->db()->getTableFields($this->table);
			}
		};
		return $this;
	}

	/**
	 * @param bool $value
	 * @return $this

	 */
	public function setIgnore($value = true) {
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
	public function setKey($field) {
		$this->keyField = $field;
		return $this;
	}

	/**
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @throws UnexpectedValueException
	 * @return $this
	 */
	public function add($field, $value) {
		if ($this->isFieldNameValid($field)) {
			throw new UnexpectedValueException('Field name is invalid');
		}
		$sqlField = $field;
		$sqlValue = $this->db()->quote($value);
		$this->fields[$sqlField] = $sqlValue;
		return $this;
	}

	/**
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @throws UnexpectedValueException
	 * @return $this
	 */
	public function update($field, $value) {
		if ($this->isFieldNameValid($field)) {
			throw new UnexpectedValueException('Field name is invalid');
		}
		$sqlField = $field;
		$sqlValue = $this->db()->quote($value);
		$this->update[$sqlField] = $sqlValue;
		return $this;
	}

	/**
	 * @param string $field
	 * @param bool|int|float|string $value
	 * @throws UnexpectedValueException
	 * @return $this
	 */
	public function addOrUpdate($field, $value) {
		$this->add($field, $value);
		$this->update($field, $value);
		return $this;
	}

	/**
	 * @param string $str
	 * @return $this
	 */
	public function addExpr($str) {
		$this->fields[] = $str;
		return $this;
	}

	/**
	 * @param string $str
	 * @return $this
	 */
	public function updateExpr($str) {
		$this->update[] = $str;
		return $this;
	}

	/**
	 * @param string $str
	 * @return $this
	 */
	public function addOrUpdateExpr($str) {
		$this->addExpr($str);
		$this->updateExpr($str);
		return $this;
	}

	/**
	 * @param array $data
	 * @return $this
	 * @throws UnexpectedValueException
	 */
	public function addAll(array $data) {
		$data = $this->clearValues($data);
		foreach ($data as $field => $value) {
			$this->add($field, $value);
		}
		return $this;
	}

	/**
	 * @param array $data
	 * @throws UnexpectedValueException
	 * @return $this
	 */
	public function updateAll(array $data) {
		$data = $this->clearValues($data);
		foreach ($data as $field => $value) {
			if ($field != $this->keyField) {
				$this->update($field, $value);
			}
		}
		return $this;
	}

	/**
	 * @param array $data
	 * @throws UnexpectedValueException
	 * @return $this
	 */
	public function addOrUpdateAll(array $data) {
		$this->addAll($data);
		$this->updateAll($data);
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
	 * @throws Exception
	 * @return string
	 */
	public function __toString() {
		if ($this->table === null) {
			throw new Exception('Specify a table-name');
		}

		$tableName = (new AliasReplacer($this->db()->getAliasRegistry()))->replace($this->table);

		$queryArr = array();
		$ignoreStr = $this->ignore ? ' IGNORE' : '';
		$queryArr[] = "INSERT{$ignoreStr} INTO\n\t{$tableName}\n";

		if($this->from !== null) {
			$fields = $this->from->getFields();
			$queryArr[] = sprintf("\t(%s)\n", join(', ', array_keys($fields)));
			$queryArr[] = trim(trim($this->from), ';');
		} else {
			$fields = $this->fields;
			$insertData = $this->buildFieldList($fields);
			if (!count($insertData)) {
				throw new Exception('No field-data found');
			}
			$queryArr[] = sprintf("SET\n%s\n", join(",\n", $insertData));
		}

		$updateData = $this->buildUpdate();
		if($updateData) {
			$queryArr[] = "{$updateData}\n";
		}

		$queryArr[] = ";\n";

		$query = join('', $queryArr);

		return $query;
	}

	/**
	 * @return string
	 */
	private function buildUpdate() {
		$queryArr = array();
		if(!empty($this->update)) {
			$queryArr[] = "ON DUPLICATE KEY UPDATE\n";
			$updateArr = array();
			if($this->keyField !== null) {
				$updateArr[] = "\t`{$this->keyField}` = LAST_INSERT_ID({$this->keyField})";
			}
			$updateArr = $this->buildFieldList($this->update, $updateArr);

			$queryArr[] = join(",\n", $updateArr);
		}
		return join('', $queryArr);
	}

	/**
	 * @param string $fieldName
	 * @return bool
	 */
	private function isFieldNameValid($fieldName) {
		return is_numeric($fieldName) || !is_scalar($fieldName);
	}

	/**
	 * @param array $values
	 * @return array
	 * @throws Exception
	 */
	private function clearValues(array $values) {
		if(!count($values)) {
			return [];
		}
		$fields = $this->db()->getTableFields($this->table);
		$result = array();

		foreach ($values as $fieldName => $fieldValue) {
			if(in_array($fieldName, $fields)) {
				$result[$fieldName] = $fieldValue;
			}
		}

		return $result;
	}
}