<?php

class jeedom {

	public static $expressions = array();

	public static function reset() {
		self::$expressions = array();
	}

	public static function evaluateExpression($_input) {
		if (array_key_exists((string) $_input, self::$expressions)) {
			return self::$expressions[(string) $_input];
		}
		$result = preg_replace_callback('/#(\d+)#/', function ($matches) {
			$cmd = cmd::byId($matches[1]);
			return is_object($cmd) ? $cmd->execCmd() : $matches[0];
		}, (string) $_input);
		if (is_numeric($result)) {
			return $result + 0;
		}
		return $result;
	}
}
