<?php
namespace Kir\MySQL\Builder\Traits;

use Kir\MySQL\Database;

trait TableNameBuilder {
	use AbstractAliasReplacer;

	/**
	 * @param string $alias
	 * @param string $name
	 * @return string
	 */
	protected function buildTableName($alias, $name) {
		if(is_object($name)) {
			$name = (string) $name;
			$lines = explode("\n", $name);
			foreach($lines as &$line) {
				$line = "\t{$line}";
			}
			$name = join("\n", $lines);
			$name = '(' . trim(rtrim(trim($name), ';')) . ')';
		}
		if(is_array($name)) {
			$parts = [];
			foreach($name as $bucket) {
				$values = [];
				foreach($bucket as $field => $value) {
					$values[] = sprintf('%s AS %s', $this->db()->quote($value), $this->db()->quoteField($field));
				}
				$parts[] = sprintf("SELECT %s", join(', ', $values));
			}
			$name = '(' . join("\n\tUNION\n\t", $parts) . ')';
		}
		$name = $this->aliasReplacer()->replace($name);
		if($alias !== null) {
			return sprintf("%s %s", $name, $alias);
		}
		return $name;
	}
	
	/**
	 * @return Database
	 */
	abstract protected function db();
}
