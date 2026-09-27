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

interface thermostatDisplay {

	public function status();

	public function setStatus($_status);

	public function mode();

	public function setMode($_mode);

	public function setpoint();

	public function setSetpoint($_value);

	public function historizeSetpoint($_value);

	public function setActive($_active);

	public function power();

	public function setPower($_power);
}
