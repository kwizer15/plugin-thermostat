<?php

foreach (array('RecordingLog', 'InMemorySettings', 'InMemoryMemory', 'FixedSensors', 'ScriptedCalendar', 'NumericEvaluator', 'RecordingScheduling') as $class) {
	require_once __DIR__ . '/' . $class . '.php';
}

function setMetierNow($_datetime) {
	file_put_contents(getenv('FAKETIME_TIMESTAMP_FILE'), $_datetime);
}
