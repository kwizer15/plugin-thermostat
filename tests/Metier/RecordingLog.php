<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\Log;

class RecordingLog implements Log {

	public $lines = array();

	public function debug($_message) {
		$this->lines[] = 'debug ' . $_message;
	}

	public function error($_message) {
		$this->lines[] = 'error ' . $_message;
	}
}
