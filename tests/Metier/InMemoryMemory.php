<?php

class InMemoryMemory implements thermostatPowerMemory {

	public $values = array(
		'lastState' => '',
		'last_power' => 0,
		'temp_threshold' => 0,
	);

	public function __construct(array $_values = array()) {
		$this->values = array_merge($this->values, $_values);
	}

	public function lastState() {
		return $this->values['lastState'];
	}

	public function lastPower() {
		return $this->values['last_power'];
	}

	public function temperatureAlert() {
		return $this->values['temp_threshold'];
	}

	public function setTemperatureAlert($_alert) {
		$this->values['temp_threshold'] = $_alert;
	}
}
