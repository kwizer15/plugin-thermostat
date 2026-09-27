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

namespace Jeedom\Plugin\Thermostat\Domain\Cycle;

class Planner {

	/**
	 * @param int|float $_power
	 * @param scalar|null $_cycle
	 * @param bool $_wasHeating
	 * @param float $_minCycleDuration
	 * @param int|string $_stoveBoiler
	 */
	public function plan($_power, $_cycle, $_wasHeating, $_minCycleDuration, $_stoveBoiler): Plan {
		$duration = round(($_power * $_cycle) / 100);
		$belowMinCycle = ($_power < $_minCycleDuration);
		$tooShort = ($belowMinCycle && ($_stoveBoiler == 0 || !$_wasHeating)) || ($_wasHeating && $_power < 1);
		$stop = Plan::STOP_UNCHANGED;
		if ($duration > 0 && $duration < $_cycle) {
			$stop = ($_stoveBoiler == 0) ? Plan::STOP_AFTER : Plan::STOP_CANCEL;
		}
		if ($duration >= $_cycle) {
			$stop = Plan::STOP_CANCEL;
		}
		return new Plan($duration, $tooShort, $stop);
	}
}
