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

namespace Jeedom\Plugin\Thermostat\Domain\Statistics;

interface History {

	/**
	 * @param string $_start
	 * @param string $_end
	 * @return array<string, mixed>|null
	 */
	public function outdoorStatistics($_start, $_end);

	/**
	 * @param string $_start
	 * @param string $_end
	 * @return list<array{datetime: string, value: mixed}>|null
	 */
	public function activeHistory($_start, $_end);

	/**
	 * @return bool
	 */
	public function hasPerformance();

	/**
	 * @param int|float $_performance
	 * @return void
	 */
	public function publishPerformance($_performance);
}
