<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Settings as ActuatorSettings;
use Jeedom\Plugin\Thermostat\Domain\Command\Settings as CommandSettings;
use Jeedom\Plugin\Thermostat\Domain\Engine\HysteresisSettings;
use Jeedom\Plugin\Thermostat\Domain\Engine\Settings as EngineSettings;
use Jeedom\Plugin\Thermostat\Domain\Learning\Settings as LearningSettings;
use Jeedom\Plugin\Thermostat\Domain\Power\Settings as PowerSettings;
use Jeedom\Plugin\Thermostat\Domain\SensorWatch\Settings as SensorWatchSettings;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\Settings as SmartStartSettings;
use Jeedom\Plugin\Thermostat\Domain\Statistics\Settings as StatisticsSettings;
use Jeedom\Plugin\Thermostat\Domain\Window\Settings as WindowSettings;

class InMemorySettings implements PowerSettings, LearningSettings, SmartStartSettings, HysteresisSettings, ActuatorSettings, WindowSettings, EngineSettings, StatisticsSettings, SensorWatchSettings, CommandSettings {

	public $values = array(
		'coeff_indoor_heat' => 10,
		'coeff_indoor_cool' => 10,
		'coeff_outdoor_heat' => 2,
		'coeff_outdoor_cool' => 2,
		'offset_heat' => 0,
		'offset_cool' => 0,
		'direction::delta::heat' => 0,
		'direction::delta::cool' => 0,
		'offset_nextFullCyle' => null,
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
		'allow_mode' => 'all',
		'positiveHysteresis' => 0,
		'hysteresis_threshold' => 1,
		'heating' => array(array('cmd' => '#heater#')),
		'cooling' => array(array('cmd' => '#cooler#')),
		'stoping' => array(array('cmd' => '#stopper#')),
		'orderChange' => array(),
		'failure' => array(),
		'failureActuator' => array(),
		'existingMode' => array(),
		'window' => array(),
		'window_alertIfOpenMoreThan' => null,
		'maxTimeUpdateTemp' => 60,
		'smart_start' => 0,
		'stove_boiler' => 0,
		'minCycleDuration' => 5,
		'offsetHeatFaillure' => 1,
		'offsetColdFaillure' => 1,
		'consumption' => '',
		'temperature_indoor_min' => null,
		'temperature_indoor_max' => null,
	);

	public $published = array();

	public function __construct(array $_values = array()) {
		$this->values = array_merge($this->values, $_values);
	}

	public function coefficientIndoor($_direction): float {
		return $this->values[($_direction > 0) ? 'coeff_indoor_heat' : 'coeff_indoor_cool'];
	}

	public function coefficientOutdoor($_direction): float {
		return $this->values[($_direction > 0) ? 'coeff_outdoor_heat' : 'coeff_outdoor_cool'];
	}

	public function offset($_direction): float {
		return $this->values[($_direction > 0) ? 'offset_heat' : 'offset_cool'];
	}

	public function directionDeltaHeat(): float {
		return $this->values['direction::delta::heat'];
	}

	public function directionDeltaCool(): float {
		return $this->values['direction::delta::cool'];
	}

	public function nextFullCycleOffset() {
		return $this->values['offset_nextFullCyle'];
	}

	public function heatHotThreshold(): float {
		return $this->values['threshold_heathot'];
	}

	public function autolearn(): bool {
		return $this->values['autolearn'];
	}

	public function cycleEndDate() {
		return $this->values['endDate'];
	}

	public function coefficient($_key): float {
		return $this->values[$_key];
	}

	public function learnedCount($_key): int {
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

	public function anticipationFactor(): float {
		return $this->values['smart_start_factor'];
	}

	public function anticipationCount(): int {
		return $this->values['smart_start_autolearn'];
	}

	public function storeAnticipation($_factor, $_count) {
		$this->values['smart_start_factor'] = $_factor;
		$this->values['smart_start_autolearn'] = $_count;
		$this->published['smart_start_factor'] = $_factor;
	}

	public function allowMode() {
		return $this->values['allow_mode'];
	}

	public function positiveHysteresis(): bool {
		return $this->values['positiveHysteresis'];
	}

	public function hysteresisThreshold(): float {
		return $this->values['hysteresis_threshold'];
	}

	public function heatingActions() {
		return $this->values['heating'];
	}

	public function coolingActions() {
		return $this->values['cooling'];
	}

	public function stoppingActions() {
		return $this->values['stoping'];
	}

	public function orderChangeActions() {
		return $this->values['orderChange'];
	}

	public function failureActions() {
		return $this->values['failure'];
	}

	public function failureActuatorActions() {
		return $this->values['failureActuator'];
	}

	public function modes() {
		return $this->values['existingMode'];
	}

	public function windows() {
		return $this->values['window'];
	}

	public function windowAlertDelay() {
		return $this->values['window_alertIfOpenMoreThan'];
	}

	public function maxTimeUpdateTemp() {
		return $this->values['maxTimeUpdateTemp'];
	}

	public function smartStartEnabled() {
		return $this->values['smart_start'] == 1;
	}

	public function stoveBoiler(): bool {
		return $this->values['stove_boiler'];
	}

	public function minCycleDuration(): float {
		return $this->values['minCycleDuration'];
	}

	public function heatFailureOffset(): float {
		return $this->values['offsetHeatFaillure'];
	}

	public function coldFailureOffset(): float {
		return $this->values['offsetColdFaillure'];
	}

	public function setCycleEndDate($_datetime) {
		$this->values['endDate'] = $_datetime;
	}

	public function consumption() {
		return $this->values['consumption'];
	}

	public function indoorMinimum() {
		return $this->values['temperature_indoor_min'];
	}

	public function indoorMaximum() {
		return $this->values['temperature_indoor_max'];
	}

	public function setOffset($_key, $_value) {
		$this->values[$_key] = $_value;
	}

	public function setAllowMode($_mode) {
		$this->values['allow_mode'] = $_mode;
	}
}
