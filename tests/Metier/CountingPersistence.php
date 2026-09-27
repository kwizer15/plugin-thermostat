<?php

use Jeedom\Plugin\Thermostat\Domain\Persistence;

class CountingPersistence implements Persistence {

	public $reloads = 0;
	public $persists = 0;
	public $fullSaves = 0;

	public function reload() {
		$this->reloads++;
	}

	public function persist() {
		$this->persists++;
	}

	public function saveWithCommands() {
		$this->fullSaves++;
	}
}
