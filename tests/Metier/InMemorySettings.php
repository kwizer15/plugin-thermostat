<?php

class InMemorySettings implements thermostatPowerSettings {

	public $values = array(
		'coeff_indoor_heat' => 10,
		'coeff_indoor_cool' => 10,
		'coeff_outdoor_heat' => 2,
		'coeff_outdoor_cool' => 2,
		'offset_heat' => 0,
		'offset_cool' => 0,
		'direction::delta::heat' => 0,
		'direction::delta::cool' => 0,
		'offset_nextFullCyle' => '',
		'threshold_heathot' => 100,
	);

	public function __construct(array $_values = array()) {
		$this->values = array_merge($this->values, $_values);
	}

	public function coefficientIndoor($_direction) {
		return $this->values[($_direction > 0) ? 'coeff_indoor_heat' : 'coeff_indoor_cool'];
	}

	public function coefficientOutdoor($_direction) {
		return $this->values[($_direction > 0) ? 'coeff_outdoor_heat' : 'coeff_outdoor_cool'];
	}

	public function offset($_direction) {
		return $this->values[($_direction > 0) ? 'offset_heat' : 'offset_cool'];
	}

	public function directionDeltaHeat() {
		return $this->values['direction::delta::heat'];
	}

	public function directionDeltaCool() {
		return $this->values['direction::delta::cool'];
	}

	public function nextFullCycleOffset() {
		return $this->values['offset_nextFullCyle'];
	}

	public function heatHotThreshold() {
		return $this->values['threshold_heathot'];
	}
}
