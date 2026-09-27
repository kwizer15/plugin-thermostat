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

namespace Jeedom\Plugin\Thermostat\Domain\Power;

interface Memory {

	/**
	 * @return string
	 */
	public function lastState();

	public function lastPower(): float;

	public function temperatureAlert(): int;

	/**
	 * @param int $_alert
	 * @return void
	 */
	public function setTemperatureAlert($_alert);
}
