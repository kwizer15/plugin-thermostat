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

interface Memory {

	public function lastState();

	public function setLastState($_state);

	public function temperatureAlert();

	public function setTemperatureAlert($_alert);

	public function lastOrder();

	public function setLastOrder($_order);

	public function lastTempIn();

	public function setLastTempIn($_temperature);

	public function setLastTempOut($_temperature);

	public function setLastPower($_power);

	public function consecutiveFailures();

	public function setConsecutiveFailures($_count);

	public function deltaOrder();
}
