<?php

namespace Jeedom\Plugin\Thermostat\Tests\Double;

use Jeedom\Plugin\Thermostat\Domain\Reading;
use Jeedom\Plugin\Thermostat\Domain\Sensors;

class FixedSensors implements Sensors {

	public $indoor;
	public $outdoor;
	public $collectDate = '';

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

	public function indoorReading(): Reading {
		return new Reading($this->indoor, $this->collectDate, $this->collectDate);
	}
}
