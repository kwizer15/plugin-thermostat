<?php

class InMemoryMemory implements thermostatPowerMemory, thermostatCycleMemory, thermostatSmartStartMemory, thermostatStateMemory {

	public $values = array(
		'lastState' => '',
		'last_power' => 0,
		'temp_threshold' => 0,
		'lastOrder' => 0,
		'lastTempIn' => 0,
		'nbConsecutiveFaillure' => 0,
		'smartStart' => '',
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

	public function lastOrder() {
		return $this->values['lastOrder'];
	}

	public function lastTempIn() {
		return $this->values['lastTempIn'];
	}

	public function consecutiveFailures() {
		return $this->values['nbConsecutiveFaillure'];
	}

	public function temperatureAlert() {
		return $this->values['temp_threshold'];
	}

	public function setTemperatureAlert($_alert) {
		$this->values['temp_threshold'] = $_alert;
	}

	public function smartStart() {
		return $this->values['smartStart'];
	}

	public function setSmartStart($_smartStart) {
		$this->values['smartStart'] = $_smartStart;
	}

	public function setLastState($_state) {
		$this->values['lastState'] = $_state;
	}
}
