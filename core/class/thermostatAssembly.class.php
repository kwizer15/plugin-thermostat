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

class thermostatAssembly {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function log() {
		return new thermostatJeedomLog($this->thermostat->getHumanName());
	}

	public function translator($_class) {
		return new thermostatJeedomTranslator(dirname(__FILE__) . '/' . $_class . '.class.php');
	}

	public function statusLabels() {
		return new thermostatStatusLabels($this->translator('thermostatStatusLabels'));
	}

	public function settings() {
		return new thermostatJeedomSettings($this->thermostat);
	}

	public function memory() {
		return new thermostatJeedomMemory($this->thermostat);
	}

	public function sensors() {
		return new thermostatJeedomSensors($this->thermostat);
	}

	public function evaluator() {
		return new thermostatJeedomEvaluator();
	}

	public function calendar() {
		return new thermostatJeedomCalendar($this->thermostat, $this->log(), $this->translator('thermostatJeedomCalendar'));
	}

	public function persistence() {
		return new thermostatJeedomPersistence($this->thermostat);
	}

	public function display() {
		return new thermostatJeedomDisplay($this->thermostat);
	}

	public function engineRunner() {
		return new thermostatJeedomEngineRunner($this->thermostat);
	}

	public function actionList() {
		return new thermostatActionList($this->thermostat, $this->log(), $this->translator('thermostatActionList'));
	}

	public function powerCalculator() {
		return new thermostatPowerCalculator($this->settings(), $this->memory(), $this->log(), $this->translator('thermostatPowerCalculator'));
	}

	public function coefficientLearner() {
		return new thermostatCoefficientLearner($this->settings(), $this->memory(), $this->log(), $this->translator('thermostatCoefficientLearner'));
	}

	public function scheduler() {
		return new thermostatScheduler($this->thermostat, $this->log());
	}

	public function smartStart() {
		return new thermostatSmartStart($this->settings(), $this->memory(), $this->calendar(), $this->sensors(), $this->display(), new thermostatJeedomControls($this->thermostat), $this->evaluator(), $this->powerCalculator(), $this->scheduler(), $this->log(), $this->translator('thermostatSmartStart'));
	}

	public function actuator() {
		return new thermostatActuator($this->settings(), $this->memory(), $this->persistence(), $this->display(), $this->actionList(), $this->engineRunner(), $this->log(), $this->statusLabels(), $this->translator('thermostatActuator'));
	}

	public function windows() {
		return new thermostatWindows($this->settings(), $this->memory(), $this->display(), new thermostatJeedomWindowSensors(), $this->actuator(), $this->engineRunner(), $this->log(), $this->statusLabels(), $this->translator('thermostatWindows'));
	}

	public function temporalEngine() {
		return new thermostatTemporalEngine($this->settings(), $this->memory(), $this->persistence(), $this->evaluator(), $this->display(), $this->sensors(), $this->actuator(), $this->scheduler(), $this->powerCalculator(), $this->smartStart(), $this->coefficientLearner(), new thermostatCyclePlanner(), $this->log(), $this->statusLabels(), $this->translator('thermostatTemporalEngine'));
	}

	public function hysteresisEngine() {
		return new thermostatHysteresisEngine($this->settings(), $this->memory(), $this->display(), $this->sensors(), $this->actuator(), new thermostatHysteresisDecision($this->settings(), $this->log(), $this->statusLabels(), $this->translator('thermostatHysteresisDecision')), $this->log(), $this->statusLabels(), $this->translator('thermostatHysteresisEngine'));
	}

	public function sensorWatch() {
		return new thermostatSensorWatch($this->settings(), $this->memory(), $this->display(), $this->sensors(), $this->actuator(), $this->log(), $this->translator('thermostatSensorWatch'));
	}

	public function commandHandler() {
		return new thermostatCommandHandler($this->settings(), $this->memory(), $this->persistence(), $this->display(), $this->actuator(), $this->engineRunner(), $this->statusLabels());
	}

	public function commands() {
		return new thermostatCommands($this->thermostat, $this->scheduler(), $this->translator('thermostatCommands'));
	}

	public function configuration() {
		return new thermostatConfiguration($this->settings(), $this->translator('thermostatConfiguration'));
	}

	public function statistics() {
		return new thermostatStatistics($this->settings(), $this->evaluator(), new thermostatJeedomHistory($this->thermostat));
	}
}
