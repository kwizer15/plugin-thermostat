<?php

namespace Jeedom\Plugin\Thermostat\Tests\Double;

use Jeedom\Plugin\Thermostat\Domain\Window\Timer;

class RecordingWindowTimer implements Timer {

	public $calls = array();

	public function schedule($_cmdId, $_phase, $_timestamp) {
		$this->calls[] = array($_cmdId, $_phase, $_timestamp);
	}
}
