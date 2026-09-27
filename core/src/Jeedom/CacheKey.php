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

final class CacheKey {

	const LAST_STATE = 'lastState';
	const LAST_POWER = 'last_power';
	const LAST_ORDER = 'lastOrder';
	const LAST_TEMP_IN = 'lastTempIn';
	const LAST_TEMP_OUT = 'lastTempOut';
	const CONSECUTIVE_FAILURES = 'nbConsecutiveFaillure';
	const TEMPERATURE_ALERT = 'temp_threshold';
	const SMART_START = 'smartStart';
	const WINDOW_OPEN_SINCE = 'window::state::open';
	const WINDOW_STATE_PREFIX = 'window::state::';
	const WINDOW_OPEN_PREFIX = 'window::open::';
	const WINDOW_CLOSE_PREFIX = 'window::close::';
	const WINDOW_ALERT_SENT = 'alertSendForWindow';
	const DELTA_ORDER = 'deltaOrder';
	const WINDOW_DATETIME_SUFFIX = '::datetime';

	private function __construct() {
	}
}
