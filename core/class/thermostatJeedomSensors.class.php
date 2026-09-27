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

class thermostatJeedomSensors implements thermostatSensors {

	private $eqLogic;

	public function __construct($_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function indoorTemperature() {
		return $this->eqLogic->getCmd(null, 'temperature')->execCmd();
	}

	public function outdoorTemperature() {
		return $this->eqLogic->getCmd(null, 'temperature_outdoor')->execCmd();
	}
	public function indoorReading() {
		$cmd = $this->eqLogic->getCmd(null, 'temperature');
		$value = $cmd->execCmd();
		return new thermostatReading($value, $cmd->getCollectDate(), $cmd->getValueDate());
	}
}
