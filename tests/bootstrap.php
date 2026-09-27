<?php

if (getenv('FAKETIME_TIMESTAMP_FILE') === false) {
	fwrite(STDERR, "Les tests se lancent dans le conteneur (make tests) : l'heure y est pilotée par libfaketime.\n");
	exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/class/thermostat.class.php';
