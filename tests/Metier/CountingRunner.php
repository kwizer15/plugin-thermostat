<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\EngineRunner;

class CountingRunner implements EngineRunner {

	public $runs = 0;

	public function run() {
		$this->runs++;
	}
}
