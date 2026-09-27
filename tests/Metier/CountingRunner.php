<?php

use Jeedom\Plugin\Thermostat\Domain\EngineRunner;

class CountingRunner implements EngineRunner {

	public $runs = 0;

	public function run() {
		$this->runs++;
	}
}
