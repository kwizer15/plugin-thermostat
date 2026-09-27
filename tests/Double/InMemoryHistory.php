<?php

namespace Jeedom\Plugin\Thermostat\Tests\Double;

use Jeedom\Plugin\Thermostat\Domain\Statistics\History;

class InMemoryHistory implements History {

	public $statistics = array();
	public $active = array();
	public $performance = false;
	public $requested = array();

	public function outdoorStatistics($_start, $_end) {
		$this->requested[] = $_start . ' / ' . $_end;
		return $this->statistics;
	}

	public function activeHistory($_start, $_end) {
		return $this->active;
	}

	public function hasPerformance() {
		return $this->performance !== false;
	}

	public function publishPerformance($_performance) {
		$this->performance = $_performance;
	}
}
