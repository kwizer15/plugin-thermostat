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

namespace Jeedom\Plugin\Thermostat\Domain\Engine;

use Jeedom\Plugin\Thermostat\Domain\HeatingAction;

interface Memory {

	/**
	 * @return string
	 */
	public function lastState();

	/**
	 * @param HeatingAction::HEAT|HeatingAction::COOL|HeatingAction::STOP $_state
	 * @return void
	 */
	public function setLastState($_state);

	/**
	 * @return int|float|string
	 */
	public function temperatureAlert();

	/**
	 * @param int $_alert
	 * @return void
	 */
	public function setTemperatureAlert($_alert);

	/**
	 * @return int|float|string
	 */
	public function lastOrder();

	/**
	 * @param scalar|null $_order
	 * @return void
	 */
	public function setLastOrder($_order);

	/**
	 * @return int|float|string
	 */
	public function lastTempIn();

	/**
	 * @param scalar|null $_temperature
	 * @return void
	 */
	public function setLastTempIn($_temperature);

	/**
	 * @param scalar|null $_temperature
	 * @return void
	 */
	public function setLastTempOut($_temperature);

	/**
	 * @param int|float $_power
	 * @return void
	 */
	public function setLastPower($_power);

	/**
	 * @return int|float|string
	 */
	public function consecutiveFailures();

	/**
	 * @param int|float $_count
	 * @return void
	 */
	public function setConsecutiveFailures($_count);

	/**
	 * @return int|float|string
	 */
	public function deltaOrder();
}
