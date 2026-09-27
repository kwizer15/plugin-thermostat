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

class thermostatJeedomSettings implements thermostatPowerSettings {

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
}
