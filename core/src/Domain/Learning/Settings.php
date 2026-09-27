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

namespace Jeedom\Plugin\Thermostat\Domain\Learning;

interface Settings {

	/**
	 * @return int|string
	 */
	public function autolearn();

	/**
	 * @return string
	 */
	public function cycleEndDate();

	/**
	 * @param string $_key
	 */
	public function coefficient($_key): float;

	/**
	 * @param string $_key
	 * @return int|float|string
	 */
	public function learnedCount($_key);

	/**
	 * @param string $_key
	 * @param int|float $_coefficient
	 * @param int|float $_count
	 * @return void
	 */
	public function storeCoefficient($_key, $_coefficient, $_count);
}
