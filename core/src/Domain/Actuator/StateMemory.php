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

namespace Jeedom\Plugin\Thermostat\Domain\Actuator;

use Jeedom\Plugin\Thermostat\Domain\HeatingAction;

interface StateMemory {

	/**
	 * @return string
	 */
	public function lastState();

	/**
	 * @param HeatingAction::HEAT|HeatingAction::COOL|HeatingAction::STOP $_state
	 * @return void
	 */
	public function setLastState($_state);
}
