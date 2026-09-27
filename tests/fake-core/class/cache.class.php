<?php

class cache {

	private static $store = array();

	private $value;

	public static function reset() {
		self::$store = array();
	}

	public static function byKey($_key) {
		$cache = new self();
		$cache->value = isset(self::$store[$_key]) ? self::$store[$_key] : '';
		return $cache;
	}

	public static function set($_key, $_value) {
		self::$store[$_key] = $_value;
	}

	public function getValue($_default = '') {
		return ($this->value === '' || $this->value === null) ? $_default : $this->value;
	}
}
