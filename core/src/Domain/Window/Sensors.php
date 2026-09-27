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

namespace Jeedom\Plugin\Thermostat\Domain\Window;

use Jeedom\Plugin\Thermostat\Domain\Reading;

interface Sensors {

	/**
	 * @param string $_cmd
	 * @return string
	 */
	public function name($_cmd);

	/**
	 * @param int|string $_cmdId
	 * @return bool
	 */
	public function exists($_cmdId);

	/**
	 * @param int|string $_cmdId
	 * @return Reading|null
	 */
	public function read($_cmdId);
}
