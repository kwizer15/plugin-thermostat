<?php

use Jeedom\Plugin\Thermostat\Domain\Actuator\StateMemory;
use Jeedom\Plugin\Thermostat\Domain\Command\Memory as CommandMemory;
use Jeedom\Plugin\Thermostat\Domain\Engine\Memory as EngineMemory;
use Jeedom\Plugin\Thermostat\Domain\Learning\CycleMemory;
use Jeedom\Plugin\Thermostat\Domain\Power\Memory as PowerMemory;
use Jeedom\Plugin\Thermostat\Domain\SensorWatch\Memory as SensorWatchMemory;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\Memory as SmartStartMemory;
use Jeedom\Plugin\Thermostat\Domain\Window\Memory as WindowMemory;

class InMemoryMemory implements PowerMemory, CycleMemory, SmartStartMemory, StateMemory, WindowMemory, EngineMemory, SensorWatchMemory, CommandMemory {

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
		'lastTempOut' => '',
		'deltaOrder' => 0,
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

	public function setLastOrder($_order) {
		$this->values['lastOrder'] = $_order;
	}

	public function setLastTempIn($_temperature) {
		$this->values['lastTempIn'] = $_temperature;
	}

	public function setLastTempOut($_temperature) {
		$this->values['lastTempOut'] = $_temperature;
	}

	public function setLastPower($_power) {
		$this->values['last_power'] = $_power;
	}

	public function setConsecutiveFailures($_count) {
		$this->values['nbConsecutiveFaillure'] = $_count;
	}

	public function deltaOrder() {
		return $this->values['deltaOrder'];
	}

	public function setDeltaOrder($_delta) {
		$this->values['deltaOrder'] = $_delta;
	}
}
