<?php

use Jeedom\Plugin\Thermostat\Domain\Display;

class InMemoryDisplay implements Display {

	public $status = 'Arrêté';
	public $mode = 'Aucun';
	public $setpoint = 20;
	public $active = 0;
	public $power = 0;
	public $history = array();
	public $events = array();
	public $locked = false;
	public $lockState = true;
	public $widgetRefreshes = 0;

	public function status() {
		return $this->status;
	}

	public function setStatus($_status) {
		$this->events[] = 'status=' . $_status;
		$this->status = $_status;
	}

	public function mode() {
		return $this->mode;
	}

	public function setMode($_mode) {
		$this->events[] = 'mode=' . $_mode;
		$this->mode = $_mode;
	}

	public function setpoint() {
		return $this->setpoint;
	}

	public function setSetpoint($_value) {
		$this->events[] = 'setpoint=' . $_value;
		$this->setpoint = $_value;
	}

	public function historizeSetpoint($_value) {
		$this->history[] = $_value;
	}

	public function setActive($_active) {
		$this->events[] = 'active=' . $_active;
		$this->active = $_active;
	}

	public function power() {
		return $this->power;
	}

	public function setPower($_power) {
		if ($this->power === null) {
			return;
		}
		$this->events[] = 'power=' . $_power;
		$this->power = $_power;
	}

	public function locked() {
		return $this->locked;
	}

	public function hasLockState() {
		return $this->lockState;
	}

	public function lock() {
		$this->locked = true;
	}

	public function unlock() {
		$this->locked = false;
	}

	public function refreshWidget() {
		$this->widgetRefreshes++;
	}
}
