<?php

namespace Jeedom\Plugin\Thermostat\Tests\Double;

use Jeedom\Plugin\Thermostat\Domain\Scheduling;

class RecordingScheduling implements Scheduling {

	public $calls = array();

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$this->calls[] = array($_next, $_stop, $_smartThermostat);
	}
}
