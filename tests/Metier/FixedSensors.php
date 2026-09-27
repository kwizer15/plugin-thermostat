<?php

class FixedSensors implements thermostatSensors {

	public $indoor;
	public $outdoor;

	public function __construct($_indoor, $_outdoor) {
		$this->indoor = $_indoor;
		$this->outdoor = $_outdoor;
	}

	public function indoorTemperature() {
		return $this->indoor;
	}

	public function outdoorTemperature() {
		return $this->outdoor;
	}
}
