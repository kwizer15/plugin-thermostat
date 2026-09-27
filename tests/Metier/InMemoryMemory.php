<?php

class InMemoryMemory implements thermostatPowerMemory, thermostatCycleMemory, thermostatSmartStartMemory, thermostatStateMemory, thermostatWindowMemory {

	public $values = array(
		'lastState' => '',
		'last_power' => 0,
		'temp_threshold' => 0,
		'lastOrder' => 0,
		'lastTempIn' => 0,
		'nbConsecutiveFaillure' => 0,
		'smartStart' => '',
		'window::state::open' => -1,
		'alertSendForWindow' => 0,
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

	public function windowState($_cmdId) {
		return isset($this->values['window::state::' . $_cmdId]) ? $this->values['window::state::' . $_cmdId] : 0;
	}

	public function setWindowState($_cmdId, $_state) {
		$this->values['window::state::' . $_cmdId] = $_state;
	}

	public function closedAt($_cmdId) {
		return isset($this->values['window::close::' . $_cmdId . '::datetime']) ? $this->values['window::close::' . $_cmdId . '::datetime'] : '';
	}

	public function setClosedAt($_cmdId, $_datetime) {
		$this->values['window::close::' . $_cmdId . '::datetime'] = $_datetime;
	}

	public function openSince() {
		return $this->values['window::state::open'];
	}

	public function setOpenSince($_timestamp) {
		$this->values['window::state::open'] = $_timestamp;
	}

	public function alertSent() {
		return $this->values['alertSendForWindow'];
	}

	public function setAlertSent($_sent) {
		$this->values['alertSendForWindow'] = $_sent;
	}
}
