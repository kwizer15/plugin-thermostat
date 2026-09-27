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

interface Settings {

	/**
	 * @return int|float|string
	 */
	public function cycle();

	/**
	 * @return int|float|string
	 */
	public function maxTimeUpdateTemp();

	/**
	 * @return bool
	 */
	public function smartStartEnabled();

	/**
	 * @return int|string
	 */
	public function stoveBoiler();

	/**
	 * @return int|float|string
	 */
	public function minCycleDuration();

	/**
	 * @return int|float|string
	 */
	public function heatFailureOffset();

	/**
	 * @return int|float|string
	 */
	public function coldFailureOffset();

	/**
	 * @param string $_key
	 * @return int|float|string
	 */
	public function learnedCount($_key);

	/**
	 * @param string $_datetime
	 * @return void
	 */
	public function setCycleEndDate($_datetime);
}
