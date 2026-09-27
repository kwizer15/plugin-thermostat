<?php

class scenarioExpression {

	public static $calls = array();

	public static $failingCmds = array();

	public static function reset() {
		self::$calls = array();
		self::$failingCmds = array();
	}

	public static function createAndExec($_type, $_cmd, $_options = null) {
		if ($_type == 'condition') {
			return jeedom::evaluateExpression($_cmd);
		}
		if (in_array($_cmd, self::$failingCmds)) {
			throw new Exception('Échec de ' . $_cmd);
		}
		self::$calls[] = array('cmd' => $_cmd, 'options' => $_options);
		$cmd = cmd::byId(str_replace('#', '', $_cmd));
		if (is_object($cmd) && $cmd->getType() == 'action') {
			return $cmd->execCmd($_options);
		}
	}

	public static function executedCmds() {
		$return = array();
		foreach (self::$calls as $call) {
			$return[] = $call['cmd'];
		}
		return $return;
	}
}
