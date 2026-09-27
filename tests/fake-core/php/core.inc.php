<?php

foreach (array('utils', 'cache', 'log', 'jeedom', 'scenarioExpression', 'cron', 'listener', 'plugin', 'eqLogic', 'cmd', 'history', 'calendar') as $class) {
	require_once __DIR__ . '/../class/' . $class . '.class.php';
}

function __($_content, $_name = '') {
	return $_content;
}

function checkAndFixCron($_cron) {
	return $_cron;
}

function is_json($_string, $_default = false) {
	if (!is_string($_string)) {
		return $_default;
	}
	$decoded = json_decode($_string, true);
	return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $_default;
}
