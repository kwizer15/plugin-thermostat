<?php

class CountingRunner implements thermostatEngineRunner {

	public $runs = 0;

	public function run() {
		$this->runs++;
	}
}
