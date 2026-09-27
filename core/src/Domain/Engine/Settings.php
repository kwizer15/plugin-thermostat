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
	 * @return float|null
	 */
	public function maxTimeUpdateTemp();

	/**
	 * @return bool
	 */
	public function smartStartEnabled();

	public function stoveBoiler(): bool;

	public function minCycleDuration(): float;

	public function heatFailureOffset(): float;

	public function coldFailureOffset(): float;

	/**
	 * @param string $_key
	 */
	public function learnedCount($_key): int;

	/**
	 * @param string $_datetime
	 * @return void
	 */
	public function setCycleEndDate($_datetime);
}
