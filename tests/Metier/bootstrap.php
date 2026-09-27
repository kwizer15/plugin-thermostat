<?php

foreach (array('RecordingLog', 'InMemorySettings', 'InMemoryMemory') as $class) {
	require_once __DIR__ . '/' . $class . '.php';
}
