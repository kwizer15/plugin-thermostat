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

interface Settings {

	/**
	 * @param int|float $_direction
	 * @return int|float|string
	 */
	public function coefficientIndoor($_direction);

	/**
	 * @param int|float $_direction
	 * @return int|float|string
	 */
	public function coefficientOutdoor($_direction);

	/**
	 * @param int|float $_direction
	 * @return int|float|string
	 */
	public function offset($_direction);

	/**
	 * @return int|float|string
	 */
	public function directionDeltaHeat();

	/**
	 * @return int|float|string
	 */
	public function directionDeltaCool();

	/**
	 * @return int|float|string
	 */
	public function nextFullCycleOffset();

	/**
	 * @return int|float|string
	 */
	public function heatHotThreshold();
}
