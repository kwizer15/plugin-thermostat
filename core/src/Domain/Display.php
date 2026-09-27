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

namespace Jeedom\Plugin\Thermostat\Domain;

interface Display {

	/**
	 * @return scalar|null
	 */
	public function status();

	/**
	 * @param string $_status
	 * @return void
	 */
	public function setStatus($_status);

	/**
	 * @return scalar|null
	 */
	public function mode();

	/**
	 * @param string $_mode
	 * @return void
	 */
	public function setMode($_mode);

	/**
	 * @return bool
	 */
	public function isOff();

	/**
	 * @return scalar|null
	 */
	public function setpoint();

	/**
	 * @param scalar|null $_value
	 * @return void
	 */
	public function setSetpoint($_value);

	/**
	 * @param scalar|null $_value
	 * @return void
	 */
	public function historizeSetpoint($_value);

	/**
	 * @param int $_active
	 * @return void
	 */
	public function setActive($_active);

	/**
	 * @return scalar|null
	 */
	public function power();

	/**
	 * @param int|float $_power
	 * @return void
	 */
	public function setPower($_power);

	/**
	 * @return bool
	 */
	public function locked();

	/**
	 * @return bool
	 */
	public function hasLockState();

	/**
	 * @return void
	 */
	public function lock();

	/**
	 * @return void
	 */
	public function unlock();

	/**
	 * @return void
	 */
	public function refreshWidget();
}
