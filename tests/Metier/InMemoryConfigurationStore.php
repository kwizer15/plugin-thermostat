<?php

use Jeedom\Plugin\Thermostat\Domain\Configuration\Store;

class InMemoryConfigurationStore implements Store {

	public $values;
	public $heating = false;

	public function __construct(array $_values = array()) {
		$this->values = $_values;
	}

	public function value($_key, $_default = '') {
		return (isset($this->values[$_key]) && $this->values[$_key] !== '') ? $this->values[$_key] : $_default;
	}

	public function change($_key, $_value) {
		$this->values[$_key] = $_value;
	}

	public function markAsHeating() {
		$this->heating = true;
	}
}
