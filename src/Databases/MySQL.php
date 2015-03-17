<?php
namespace Kir\MySQL\Databases;

use Kir\MySQL\Builder;
use Kir\MySQL\Builder\Exception;
use Kir\MySQL\Builder\QueryStatement;
use Kir\MySQL\Builder\Statement;
use Kir\MySQL\Database;
use Kir\MySQL\QueryLogger\QueryLoggers;
use Kir\MySQL\Tools\AliasRegistry;
use Kir\MySQL\Tools\PDOStatementInterceptor;
use PDO;
use PDOStatement;
use UnexpectedValueException;
use Kir\MySQL\Builder\RunnableSelect;

/**
 */
class MySQL implements Database {
	/** @var array */
	private static $tableFields = array();
	/** @var PDO */
	private $pdo;
	/** @var AliasRegistry */
	private $aliasRegistry;
	/** @var int */
	private $transactionLevel = 0;
	/** @var QueryLoggers */
	private $queryLoggers = 0;

	/**
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo) {
		$this->pdo = $pdo;
		$this->aliasRegistry = new AliasRegistry();
		$this->queryLoggers = new QueryLoggers();
	}

	/**
	 * @return QueryLoggers
	 */
	public function getQueryLoggers() {
		return $this->queryLoggers;
	}

	/**
	 * @return AliasRegistry
	 */
	public function getAliasRegistry() {
		return $this->aliasRegistry;
	}

	/**
	 * @param string $query
	 * @throws Exception
	 * @return QueryStatement
	 */
	public function query($query) {
		$stmt = $this->pdo->query($query);
		if(!$stmt) {
			throw new Exception("Could not execute statement:\n{$query}");
		}
		$stmtWrapper = new QueryStatement($stmt, $query, $this->queryLoggers);
		return $stmtWrapper;
	}

	/**
	 * @param string $query
	 * @throws Exception
	 * @return QueryStatement
	 */
	public function prepare($query) {
		$stmt = $this->pdo->prepare($query);
		if(!$stmt) {
			throw new Exception("Could not execute statement:\n{$query}");
		}
		$stmtWrapper = new QueryStatement($stmt, $query, $this->queryLoggers);
		return $stmtWrapper;
	}

	/**
	 * @param string $query
	 * @param array $params
	 * @return int
	 */
	public function exec($query, array $params = array()) {
		$stmt = $this->pdo->prepare($query);
		$timer = microtime(true);
		$stmt->execute($params);
		$this->queryLoggers->log($query, microtime(true) - $timer);
		$result = $stmt->rowCount();
		$stmt->closeCursor();
		return $result;
	}

	/**
	 * @return int
	 */
	public function getLastInsertId() {
		return $this->pdo->lastInsertId();
	}

	/**
	 * @param string $table
	 * @return array
	 */
	public function getTableFields($table) {
		$table = $this->select()->aliasReplacer()->replace($table);
		if(array_key_exists($table, self::$tableFields)) {
			return self::$tableFields[$table];
		}
		$stmt = $this->pdo->query("DESCRIBE {$table}");
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		self::$tableFields[$table] = array_map(function ($row) { return $row['Field']; }, $rows);
		$stmt->closeCursor();
		return self::$tableFields[$table];
	}

	/**
	 * @param mixed $expression
	 * @param array $arguments
	 * @return string
	 */
	public function quoteExpression($expression, array $arguments = array()) {
		$func = function ($oldValue) use ($arguments) {
			static $idx = -1;
			$idx++;
			$index = $idx;

			if(substr($oldValue[0], 0, 1) == ':') {
				$index = (int) substr($oldValue[0], 1);
			}

			if(array_key_exists($index, $arguments)) {
				$argument = $arguments[$index];
				$value = $this->quote($argument);
			} else {
				$value = 'NULL';
			}
			return $value;
		};
		return preg_replace_callback('/(\\?|:\\d+)/', $func, $expression);
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public function quote($value) {
		if(is_null($value)) {
			$result = 'NULL';
		} elseif(is_array($value)) {
			$result = join(', ', array_map(function ($value) { return $this->quote($value); }, $value));
		/*} elseif(is_int(trim($value)) && strpos('123456789', substr(0, 1, trim($value))) !== null) {
			$result = $value;*/
		} else {
			$result = $this->pdo->quote($value);
		}
		return $result;
	}

	/**
	 * @param string $field
	 * @return string
	 */
	public function quoteField($field) {
		if (is_numeric($field) || !is_scalar($field)) {
			throw new UnexpectedValueException('Field name is invalid');
		}
		if(strpos($field, '`') !== false) {
			return $field;
		}
		$parts = explode('.', $field);
		return '`'.join('`.`', $parts).'`';
	}

	/**
	 * @param array $fields
	 * @return RunnableSelect
	 */
	public function select(array $fields = array()) {
		$select = new RunnableSelect($this);
		$select->fields($fields);
		return $select;
	}

	/**
	 * @return Builder\RunnableInsert
	 */
	public function insert() {
		return new Builder\RunnableInsert($this);
	}

	/**
	 * @return Builder\RunnableUpdate
	 */
	public function update() {
		return new Builder\RunnableUpdate($this);
	}

	/**
	 * @return Builder\RunnableDelete
	 */
	public function delete() {
		return new Builder\RunnableDelete($this);
	}

	/**
	 * @return $this
	 */
	public function transactionStart() {
		if((int) $this->transactionLevel === 0) {
			$this->pdo->beginTransaction();
		}
		$this->transactionLevel++;
		return $this;
	}

	/**
	 * @return $this
	 * @throws Exception
	 */
	public function transactionCommit() {
		$this->transactionLevel--;
		if($this->transactionLevel < 0) {
			throw new Exception("Transaction-Nesting-Problem: Trying to invoke commit on a already closed transaction");
		}
		if((int) $this->transactionLevel === 0) {
			$this->pdo->commit();
		}
		return $this;
	}

	/**
	 * @return $this
	 * @throws Exception
	 */
	public function transactionRollback() {
		$this->transactionLevel--;
		if($this->transactionLevel < 0) {
			throw new Exception("Transaction-Nesting-Problem: Trying to invoke rollback on a already closed transaction");
		}
		if((int) $this->transactionLevel === 0) {
			$this->pdo->rollBack();
		}
		return $this;
	}

	/**
	 * @param int|callable $tries
	 * @param callable|null $callback
	 * @return mixed
	 * @throws \Exception
	 */
	public function transaction($tries = 1, $callback = null) {
		if(is_callable($tries)) {
			$callback = $tries;
			$tries = 1;
		} elseif(!is_callable($callback)) {
			throw new \Exception('$callback must be a callable');
		}
		$e = null;
		for(; $tries--;) {
			try {
				$this->transactionStart();
				$result = call_user_func($callback, $this);
				$this->transactionCommit();
				return $result;
			} catch (\Exception $e) {
				$this->transactionRollback();
			}
		}
		throw $e;
	}
}