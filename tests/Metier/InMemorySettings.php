<?php

class InMemorySettings implements thermostatPowerSettings, thermostatLearningSettings, thermostatSmartStartSettings {

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
		'autolearn' => 1,
		'endDate' => '',
		'coeff_indoor_heat_autolearn' => 1,
		'coeff_indoor_cool_autolearn' => 1,
		'coeff_outdoor_heat_autolearn' => 0,
		'coeff_outdoor_cool_autolearn' => 0,
		'engine' => 'temporal',
		'cycle' => 60,
		'smart_start_factor' => 1,
		'smart_start_autolearn' => 0,
	);

	public $published = array();

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

	public function autolearn() {
		return $this->values['autolearn'];
	}

	public function cycleEndDate() {
		return $this->values['endDate'];
	}

	public function coefficient($_key) {
		return $this->values[$_key];
	}

	public function learnedCount($_key) {
		return $this->values[$_key . '_autolearn'];
	}

	public function storeCoefficient($_key, $_coefficient, $_count) {
		$this->values[$_key . '_autolearn'] = $_count;
		$this->values[$_key] = $_coefficient;
		$this->published[$_key] = $_coefficient;
	}

	public function engine() {
		return $this->values['engine'];
	}

	public function cycle() {
		return $this->values['cycle'];
	}

	public function anticipationFactor() {
		return $this->values['smart_start_factor'];
	}

	public function anticipationCount() {
		return $this->values['smart_start_autolearn'];
	}

	public function storeAnticipation($_factor, $_count) {
		$this->values['smart_start_factor'] = $_factor;
		$this->values['smart_start_autolearn'] = $_count;
		$this->published['smart_start_factor'] = $_factor;
	}
}
