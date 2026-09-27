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

class thermostatJeedomSettings implements thermostatPowerSettings, thermostatLearningSettings, thermostatSmartStartSettings, thermostatHysteresisSettings, thermostatActuatorSettings, thermostatWindowSettings, thermostatEngineSettings, thermostatConfigurationStore, thermostatStatisticsSettings, thermostatSensorWatchSettings, thermostatCommandSettings {

	private $eqLogic;

	public function __construct($_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function coefficientIndoor($_direction) {
		return ($_direction > 0) ? $this->eqLogic->getConfiguration('coeff_indoor_heat') : $this->eqLogic->getConfiguration('coeff_indoor_cool');
	}

	public function coefficientOutdoor($_direction) {
		return ($_direction > 0) ? $this->eqLogic->getConfiguration('coeff_outdoor_heat') : $this->eqLogic->getConfiguration('coeff_outdoor_cool');
	}

	public function offset($_direction) {
		return ($_direction > 0) ? $this->eqLogic->getConfiguration('offset_heat') : $this->eqLogic->getConfiguration('offset_cool');
	}

	public function directionDeltaHeat() {
		return $this->eqLogic->getConfiguration('direction::delta::heat', 0);
	}

	public function directionDeltaCool() {
		return $this->eqLogic->getConfiguration('direction::delta::cool', 0);
	}

	public function nextFullCycleOffset() {
		return $this->eqLogic->getConfiguration('offset_nextFullCyle');
	}

	public function heatHotThreshold() {
		return $this->eqLogic->getConfiguration('threshold_heathot', 100);
	}

	public function autolearn() {
		return $this->eqLogic->getConfiguration('autolearn');
	}

	public function cycleEndDate() {
		return $this->eqLogic->getConfiguration('endDate');
	}

	public function coefficient($_key) {
		return $this->eqLogic->getConfiguration($_key);
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
		return $this->eqLogic->getConfiguration('engine', 'temporal');
	}

	public function cycle() {
		return $this->eqLogic->getConfiguration('cycle');
	}

	public function anticipationFactor() {
		return $this->eqLogic->getConfiguration('smart_start_factor', 1);
	}

	public function anticipationCount() {
		return $this->eqLogic->getConfiguration('smart_start_autolearn', 0);
	}

	public function storeAnticipation($_factor, $_count) {
		$this->eqLogic->setConfiguration('smart_start_factor', $_factor);
		$this->eqLogic->setConfiguration('smart_start_autolearn', $_count);
		$this->eqLogic->checkAndUpdateCmd('smart_start_factor', $_factor);
	}

	public function allowMode() {
		return $this->eqLogic->getConfiguration('allow_mode', 'all');
	}

	public function positiveHysteresis() {
		return $this->eqLogic->getConfiguration('positiveHysteresis', 0);
	}

	public function hysteresisThreshold() {
		return $this->eqLogic->getConfiguration('hysteresis_threshold', 1);
	}
	public function heatingActions() {
		return $this->eqLogic->getConfiguration('heating');
	}

	public function coolingActions() {
		return $this->eqLogic->getConfiguration('cooling');
	}

	public function stoppingActions() {
		return $this->eqLogic->getConfiguration('stoping');
	}

	public function orderChangeActions() {
		return $this->eqLogic->getConfiguration('orderChange');
	}

	public function failureActions() {
		return $this->eqLogic->getConfiguration('failure');
	}

	public function failureActuatorActions() {
		return $this->eqLogic->getConfiguration('failureActuator');
	}

	public function modes() {
		return $this->eqLogic->getConfiguration('existingMode');
	}
	public function windows() {
		return $this->eqLogic->getConfiguration('window');
	}

	public function windowAlertDelay() {
		return $this->eqLogic->getConfiguration('window_alertIfOpenMoreThan');
	}
	public function maxTimeUpdateTemp() {
		return $this->eqLogic->getConfiguration('maxTimeUpdateTemp');
	}

	public function smartStartEnabled() {
		return $this->eqLogic->getConfiguration('smart_start') == 1;
	}

	public function stoveBoiler() {
		return $this->eqLogic->getConfiguration('stove_boiler');
	}

	public function minCycleDuration() {
		return $this->eqLogic->getConfiguration('minCycleDuration', 5);
	}

	public function heatFailureOffset() {
		return $this->eqLogic->getConfiguration('offsetHeatFaillure', 1);
	}

	public function coldFailureOffset() {
		return $this->eqLogic->getConfiguration('offsetColdFaillure', 1);
	}

	public function setCycleEndDate($_datetime) {
		$this->eqLogic->setConfiguration('endDate', $_datetime);
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
		return $this->eqLogic->getConfiguration('consumption');
	}
	public function indoorMinimum() {
		return $this->eqLogic->getConfiguration('temperature_indoor_min');
	}

	public function indoorMaximum() {
		return $this->eqLogic->getConfiguration('temperature_indoor_max');
	}
	public function setOffset($_key, $_value) {
		$this->eqLogic->setConfiguration($_key, $_value);
	}

	public function setAllowMode($_mode) {
		$this->eqLogic->setConfiguration('allow_mode', $_mode);
	}
}
