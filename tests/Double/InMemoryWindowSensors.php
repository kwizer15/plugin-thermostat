<?php

namespace Jeedom\Plugin\Thermostat\Tests\Double;

use Jeedom\Plugin\Thermostat\Domain\Reading;
use Jeedom\Plugin\Thermostat\Domain\Window\Sensors;

class InMemoryWindowSensors implements Sensors {

	public $values = array();
	public $valueDates = array();

	public function set($_cmdId, $_value, $_valueDate = '2026-01-15 10:00:00') {
		$this->values[$_cmdId] = $_value;
		$this->valueDates[$_cmdId] = $_valueDate;
	}

	public function name($_cmd) {
		return '[Maison][' . $_cmd . ']';
	}

	public function read($_cmdId) {
		if (!isset($this->values[$_cmdId])) {
			return null;
		}
		return new Reading($this->values[$_cmdId], '', $this->valueDates[$_cmdId]);
	}
}
