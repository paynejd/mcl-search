<?php

class Collection
{
	private $arr = array();

	public function __construct() {
		// do nothing
	}

	/**
	 * Add object to the collection
	 * @param string $key
	 * @param mixed $object
	 */
	public function Add($key, $object) {
		$this->arr[$key] = $object;
	}

	/**
	 * Get object from the collection
	 * @param string $key
	 */
	public function Get($key) {
		if (isset($this->arr[$key])) {
			return $this->arr[$key];
		}
		return null;
	}


	public function IsMember($key) {
		return isset($this->arr[$key]);
	}
	public function Remove($key) {
		if (isset($this->arr[$key])) {
			$c = $this->arr[$key];
			unset($this->arr[$key]);
			return $c;
		} else {
			trigger_error('"' . $key . '" is not a member of this collection.', E_USER_ERROR);
		}
	}
	public function getArray() {
		return $this->arr;
	}
	public function getKeys() {
		return array_keys($this->arr);
	}
	public function Count() {
		return count($this->arr);
	}
}


?>