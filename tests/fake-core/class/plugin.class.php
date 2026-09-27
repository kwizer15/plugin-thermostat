<?php

class plugin {

	private static $active = array();

	private $id;

	public static function reset() {
		self::$active = array();
	}

	public static function install($_id, $_active = 1) {
		self::$active[$_id] = $_active;
	}

	public static function byId($_id) {
		if (!isset(self::$active[$_id])) {
			throw new Exception('Plugin introuvable : ' . $_id);
		}
		$plugin = new self();
		$plugin->id = $_id;
		return $plugin;
	}

	public function isActive() {
		return self::$active[$this->id];
	}
}
