<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Jeedom\Plugin\Thermostat\Jeedom;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Settings as ActuatorSettings;
use Jeedom\Plugin\Thermostat\Domain\AllowMode;
use Jeedom\Plugin\Thermostat\Domain\Command\LogicalId;
use Jeedom\Plugin\Thermostat\Domain\Command\Settings as CommandSettings;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Key;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Store;
use Jeedom\Plugin\Thermostat\Domain\Engine\EngineType;
use Jeedom\Plugin\Thermostat\Domain\Engine\HysteresisSettings;
use Jeedom\Plugin\Thermostat\Domain\Engine\Settings as EngineSettings;
use Jeedom\Plugin\Thermostat\Domain\Learning\Settings as LearningSettings;
use Jeedom\Plugin\Thermostat\Domain\Power\Settings as PowerSettings;
use Jeedom\Plugin\Thermostat\Domain\SensorWatch\Settings as SensorWatchSettings;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\Settings as SmartStartSettings;
use Jeedom\Plugin\Thermostat\Domain\Statistics\Settings as StatisticsSettings;
use Jeedom\Plugin\Thermostat\Domain\Window\Settings as WindowSettings;

class Settings implements PowerSettings, LearningSettings, SmartStartSettings, HysteresisSettings, ActuatorSettings, WindowSettings, EngineSettings, Store, StatisticsSettings, SensorWatchSettings, CommandSettings {

	/** @var \thermostat */
	private $eqLogic;

	public function __construct(\thermostat $_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function coefficientIndoor($_direction): float {
		return $this->coefficient(($_direction > 0) ? Key::COEFF_INDOOR_HEAT : Key::COEFF_INDOOR_COOL);
	}

	public function coefficientOutdoor($_direction): float {
		return $this->coefficient(($_direction > 0) ? Key::COEFF_OUTDOOR_HEAT : Key::COEFF_OUTDOOR_COOL);
	}

	public function offset($_direction): float {
		return Value::number($this->eqLogic->getConfiguration(($_direction > 0) ? Key::OFFSET_HEAT : Key::OFFSET_COOL), 0.0);
	}

	public function directionDeltaHeat(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::DIRECTION_DELTA_HEAT), 0.0);
	}

	public function directionDeltaCool(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::DIRECTION_DELTA_COOL), 0.0);
	}

	public function nextFullCycleOffset() {
		return $this->eqLogic->getConfiguration(Key::NEXT_FULL_CYCLE_OFFSET);
	}

	public function heatHotThreshold(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::HEAT_HOT_THRESHOLD), 100.0);
	}

	public function autolearn() {
		return $this->eqLogic->getConfiguration(Key::AUTOLEARN);
	}

	public function cycleEndDate() {
		return $this->eqLogic->getConfiguration(Key::CYCLE_END_DATE);
	}

	public function coefficient($_key): float {
		$default = in_array($_key, array(Key::COEFF_OUTDOOR_HEAT, Key::COEFF_OUTDOOR_COOL)) ? 2.0 : 10.0;
		return Value::number($this->eqLogic->getConfiguration($_key), $default);
	}

	public function learnedCount($_key) {
		return $this->eqLogic->getConfiguration($_key . '_autolearn');
	}

	public function storeCoefficient($_key, $_coefficient, $_count) {
		$this->eqLogic->setConfiguration($_key . '_autolearn', $_count);
		$this->eqLogic->setConfiguration($_key, $_coefficient);
		$this->eqLogic->checkAndUpdateCmd($_key, $_coefficient);
	}

	public function engine() {
		return $this->eqLogic->getConfiguration(Key::ENGINE, EngineType::TEMPORAL);
	}

	public function cycle() {
		return $this->eqLogic->getConfiguration(Key::CYCLE);
	}

	public function anticipationFactor(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::SMART_START_FACTOR), 1.0);
	}

	public function anticipationCount() {
		return $this->eqLogic->getConfiguration(Key::SMART_START_AUTOLEARN, 0);
	}

	public function storeAnticipation($_factor, $_count) {
		$this->eqLogic->setConfiguration(Key::SMART_START_FACTOR, $_factor);
		$this->eqLogic->setConfiguration(Key::SMART_START_AUTOLEARN, $_count);
		$this->eqLogic->checkAndUpdateCmd(LogicalId::SMART_START_FACTOR, $_factor);
	}

	public function allowMode() {
		return $this->eqLogic->getConfiguration(Key::ALLOW_MODE, AllowMode::ALL);
	}

	public function positiveHysteresis() {
		return $this->eqLogic->getConfiguration(Key::POSITIVE_HYSTERESIS, 0);
	}

	public function hysteresisThreshold(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::HYSTERESIS_THRESHOLD), 1.0);
	}
	public function heatingActions() {
		return $this->listValue(Key::HEATING_ACTIONS);
	}

	public function coolingActions() {
		return $this->listValue(Key::COOLING_ACTIONS);
	}

	public function stoppingActions() {
		return $this->listValue(Key::STOPPING_ACTIONS);
	}

	public function orderChangeActions() {
		return $this->listValue(Key::ORDER_CHANGE_ACTIONS);
	}

	public function failureActions() {
		return $this->listValue(Key::FAILURE_ACTIONS);
	}

	public function failureActuatorActions() {
		return $this->listValue(Key::FAILURE_ACTUATOR_ACTIONS);
	}

	public function modes() {
		return $this->listValue(Key::MODES);
	}
	public function windows() {
		return $this->listValue(Key::WINDOWS);
	}

	public function windowAlertDelay() {
		return $this->eqLogic->getConfiguration(Key::WINDOW_ALERT_DELAY);
	}
	public function maxTimeUpdateTemp() {
		return $this->eqLogic->getConfiguration(Key::MAX_TIME_UPDATE_TEMP);
	}

	public function smartStartEnabled() {
		return $this->eqLogic->getConfiguration(Key::SMART_START) == 1;
	}

	public function stoveBoiler() {
		return $this->eqLogic->getConfiguration(Key::STOVE_BOILER);
	}

	public function minCycleDuration(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::MIN_CYCLE_DURATION), 5.0);
	}

	public function heatFailureOffset(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::HEAT_FAILURE_OFFSET), 1.0);
	}

	public function coldFailureOffset(): float {
		return Value::number($this->eqLogic->getConfiguration(Key::COLD_FAILURE_OFFSET), 1.0);
	}

	public function setCycleEndDate($_datetime) {
		$this->eqLogic->setConfiguration(Key::CYCLE_END_DATE, $_datetime);
	}
	public function value($_key, $_default = '') {
		return $this->eqLogic->getConfiguration($_key, $_default);
	}

	public function change($_key, $_value) {
		$this->eqLogic->setConfiguration($_key, $_value);
	}

	public function markAsHeating() {
		$this->eqLogic->setCategory('heating', 1);
	}

	public function consumption() {
		return $this->eqLogic->getConfiguration(Key::CONSUMPTION);
	}
	public function indoorMinimum() {
		return $this->eqLogic->getConfiguration(Key::TEMPERATURE_INDOOR_MIN);
	}

	public function indoorMaximum() {
		return $this->eqLogic->getConfiguration(Key::TEMPERATURE_INDOOR_MAX);
	}
	public function setOffset($_key, $_value) {
		$this->eqLogic->setConfiguration($_key, $_value);
	}

	public function setAllowMode($_mode) {
		$this->eqLogic->setConfiguration(Key::ALLOW_MODE, $_mode);
	}

	/**
	 * @param string $_key
	 * @return array<mixed>
	 */
	private function listValue($_key) {
		$value = $this->eqLogic->getConfiguration($_key);
		return is_array($value) ? $value : array();
	}
}
