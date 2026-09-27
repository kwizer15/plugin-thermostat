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

class thermostatJeedomMemory implements thermostatPowerMemory, thermostatCycleMemory {

	private $eqLogic;

	public function __construct($_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function lastState() {
		return $this->eqLogic->getCache('lastState');
	}

	public function lastPower() {
		return $this->eqLogic->getCache('last_power', 0);
	}

	public function lastOrder() {
		return $this->eqLogic->getCache('lastOrder', 0);
	}

	public function lastTempIn() {
		return $this->eqLogic->getCache('lastTempIn', 0);
	}

	public function consecutiveFailures() {
		return $this->eqLogic->getCache('nbConsecutiveFaillure', 0);
	}

	public function temperatureAlert() {
		return $this->eqLogic->getCache('temp_threshold', 0);
	}

	public function setTemperatureAlert($_alert) {
		$this->eqLogic->setCache('temp_threshold', $_alert);
	}
}
