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
		return new thermostatJeedomCalendar($this->thermostat, $this->log());
	}

	public function actionList() {
		return new thermostatActionList($this->thermostat, $this->log());
	}

	public function powerCalculator() {
		return new thermostatPowerCalculator($this->settings(), $this->memory(), $this->log());
	}

	public function coefficientLearner() {
		return new thermostatCoefficientLearner($this->settings(), $this->memory(), $this->log());
	}

	public function scheduler() {
		return new thermostatScheduler($this->thermostat, $this->log());
	}

	public function smartStart() {
		return new thermostatSmartStart($this->settings(), $this->memory(), $this->calendar(), $this->sensors(), $this->evaluator(), $this->powerCalculator(), $this->scheduler(), $this->log());
	}

	public function actuator() {
		return new thermostatActuator($this->thermostat, $this->actionList(), $this->log());
	}

	public function windows() {
		return new thermostatWindows($this->thermostat, $this->actuator(), $this->log());
	}

	public function temporalEngine() {
		return new thermostatTemporalEngine($this->thermostat, $this->actuator(), $this->scheduler(), $this->powerCalculator(), $this->smartStart(), $this->coefficientLearner(), $this->log());
	}

	public function hysteresisEngine() {
		return new thermostatHysteresisEngine($this->thermostat, $this->actuator(), $this->log());
	}

	public function commands() {
		return new thermostatCommands($this->thermostat, $this->scheduler());
	}

	public function configuration() {
		return new thermostatConfiguration($this->thermostat);
	}

	public function statistics() {
		return new thermostatStatistics($this->thermostat);
	}
}
