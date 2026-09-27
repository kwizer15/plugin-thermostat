<?php

class log {

	public static $entries = array();

	public static function reset() {
		self::$entries = array();
	}

	public static function add($_log, $_type, $_message) {
		self::$entries[] = array('log' => $_log, 'level' => $_type, 'message' => $_message);
	}

	public static function messages($_level) {
		$return = array();
		foreach (self::$entries as $entry) {
			if ($entry['level'] == $_level) {
				$return[] = $entry['message'];
			}
		}
		return $return;
	}
}
