<?php

namespace Jeedom\Plugin\Thermostat\Tests\Double;

use Jeedom\Plugin\Thermostat\Domain\SmartStart\Controls;

class RecordingControls implements Controls {

	public $modes = array();
	public $calls = array();

	public function requestSetpoint($_value) {
		$this->calls[] = 'setpoint ' . $_value;
	}

	public function modeExists($_cmdId) {
		return in_array($_cmdId, $this->modes);
	}

	public function runMode($_cmdId) {
		$this->calls[] = 'mode ' . $_cmdId;
	}
}
