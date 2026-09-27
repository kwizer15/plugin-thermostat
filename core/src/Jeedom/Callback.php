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

namespace Jeedom\Plugin\Thermostat\Jeedom;

final class Callback {

	const PULL = 'pull';
	const WINDOW = 'window';
	const HYSTERESIS = 'hysteresis';
	const UPDATE_PERFORMANCE = 'updatePerformance';
	const WINDOW_TIMER = 'windowTimer';
	const OPTION_THERMOSTAT_ID = 'thermostat_id';
	const OPTION_STOP = 'stop';
	const OPTION_SMART_THERMOSTAT = 'smartThermostat';
	const OPTION_CMD = 'cmd';
	const OPTION_PHASE = 'phase';

	private function __construct() {
	}
}
