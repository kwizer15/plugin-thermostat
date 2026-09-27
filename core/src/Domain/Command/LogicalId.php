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

namespace Jeedom\Plugin\Thermostat\Domain\Command;

final class LogicalId {

	const ORDER = 'order';
	const THERMOSTAT = 'thermostat';
	const STATUS = 'status';
	const ACTIVE = 'actif';
	const LOCK_STATE = 'lock_state';
	const LOCK = 'lock';
	const UNLOCK = 'unlock';
	const TEMPERATURE = 'temperature';
	const TEMPERATURE_OUTDOOR = 'temperature_outdoor';
	const OFFSET_HEAT = 'offset_heat';
	const OFFSET_COOL = 'offset_cool';
	const HEAT_ONLY = 'heat_only';
	const COOL_ONLY = 'cool_only';
	const ALL_ALLOW = 'all_allow';
	const MODE = 'mode';
	const OFF = 'off';
	const COEFF_INDOOR_HEAT = 'coeff_indoor_heat';
	const COEFF_OUTDOOR_HEAT = 'coeff_outdoor_heat';
	const COEFF_INDOOR_COOL = 'coeff_indoor_cool';
	const COEFF_OUTDOOR_COOL = 'coeff_outdoor_cool';
	const SMART_START_FACTOR = 'smart_start_factor';
	const DELTA_ORDER = 'deltaOrder';
	const PERFORMANCE = 'performance';
	const POWER = 'power';
	const MODE_ACTION = 'modeAction';
	const CUSTOM_CMD = 'customCmd';

	private function __construct() {
	}
}
