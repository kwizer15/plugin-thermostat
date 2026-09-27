<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\Clock;

class FixedClock implements Clock {

	private $now;

	public function __construct($_datetime) {
		$this->now = strtotime($_datetime);
	}

	public function now() {
		return $this->now;
	}
}
