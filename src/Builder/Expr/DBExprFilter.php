<?php
namespace Kir\MySQL\Builder\Expr;

use RuntimeException;

class DBExprFilter implements OptionalExpression {
	/** @var mixed */
	private $expression;
	/** @var mixed */
	private $value;
	/** @var string[] */
	private $keyPath;
	/** @var callable|null */
	private $validator;
	/** @var callable */
	private $validationResultHandler;

	/**
	 * @param string $expression
	 * @param array $data
	 * @param string|string[] $keyPath
	 * @param callable|null $validator
	 * @param callable|null $validationResultHandler
	 */
	public function __construct($expression, array $data, $keyPath, $validator = null, $validationResultHandler = null) {
		$this->expression = $expression;
		$this->value = $data;
		$this->keyPath = $this->buildKey($keyPath);
		$this->value = $this->recursiveGet($data, $this->keyPath, null);
		if($validator === null) {
			$validator = function ($data) {
				if(is_array($data)) {
					return $this->isValidArray($data);
				}
				return (string) $data !== '';
			};
		}
		if($validationResultHandler === null) {
			$validationResultHandler = function () {};
		}
		$this->validator = $validator;
		$this->validationResultHandler = $validationResultHandler;
	}

	/**
	 * @return mixed
	 */
	public function getExpression() {
		return $this->expression;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		$result = call_user_func($this->validator, $this->value);
		call_user_func($this->validationResultHandler, $result, [
			'value' => $this->value,
			'key' => join('.', $this->keyPath),
		]);
		return $result;
	}

	/**
	 * @return mixed
	 */
	public function getValue() {
		return [$this->value];
	}

	/**
	 * @param string|string[] $keyPath
	 * @return string
	 */
	private function buildKey($keyPath) {
		if(is_string($keyPath)) {
			$keyPath = explode('.', $keyPath);
		}
		if(!is_array($keyPath)) {
			throw new RuntimeException('Invalid key');
		}
		return $keyPath;
	}

	/**
	 * @param array $array
	 * @return bool
	 */
	private function isValidArray(array $array) {
		$data = array_filter($array, function ($value) {
			if(is_array($value)) {
				return $this->isValidArray($value);
			}
			return (string) $value !== '';
		});
		return count($data) > 0;
	}

	/**
	 * @param array $array
	 * @param array $path
	 * @param mixed $default
	 * @return array
	 */
	private function recursiveGet($array, $path, $default) {
		$count = count($path);
		if (!$count) {
			return $default;
		}
		for($idx = 0; $idx < $count; $idx++) {
			$part = $path[$idx];
			if(!array_key_exists($part, $array)) {
				return $default;
			}
			$array = $array[$part];
		}
		return $array;
	}
}
