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

namespace Jeedom\Plugin\Thermostat\Domain\Configuration;

final class Key {

	const ENGINE = 'engine';
	const MODES = 'existingMode';
	const ORDER_MAX = 'order_max';
	const ORDER_MIN = 'order_min';
	const MIN_CYCLE_DURATION = 'minCycleDuration';
	const CYCLE = 'cycle';
	const TEMPERATURE_INDOOR = 'temperature_indoor';
	const TEMPERATURE_OUTDOOR = 'temperature_outdoor';
	const CUSTOM_CMD = 'customCmd';
	const COEFF_INDOOR_HEAT = 'coeff_indoor_heat';
	const COEFF_INDOOR_COOL = 'coeff_indoor_cool';
	const COEFF_OUTDOOR_HEAT = 'coeff_outdoor_heat';
	const COEFF_OUTDOOR_COOL = 'coeff_outdoor_cool';
	const COEFF_INDOOR_HEAT_AUTOLEARN = 'coeff_indoor_heat_autolearn';
	const COEFF_INDOOR_COOL_AUTOLEARN = 'coeff_indoor_cool_autolearn';
	const COEFF_OUTDOOR_HEAT_AUTOLEARN = 'coeff_outdoor_heat_autolearn';
	const COEFF_OUTDOOR_COOL_AUTOLEARN = 'coeff_outdoor_cool_autolearn';
	const OFFSET_HEAT = 'offset_heat';
	const OFFSET_COOL = 'offset_cool';
	const SMART_START = 'smart_start';
	const AUTOLEARN = 'autolearn';
	const HYSTERESIS_THRESHOLD = 'hysteresis_threshold';
	const CONSUMPTION = 'consumption';
	const REPEAT_CRON = 'repeat_commande_cron';
	const WINDOWS = 'window';
	const HYSTERESIS_CRON = 'hysteresis_cron';
	const CYCLE_END_DATE = 'endDate';
	const SMART_START_FACTOR = 'smart_start_factor';
	const SMART_START_AUTOLEARN = 'smart_start_autolearn';
	const ALLOW_MODE = 'allow_mode';
	const HIDE_LOCK_CMD = 'hideLockCmd';
	const DIRECTION_DELTA_HEAT = 'direction::delta::heat';
	const DIRECTION_DELTA_COOL = 'direction::delta::cool';
	const NEXT_FULL_CYCLE_OFFSET = 'offset_nextFullCyle';
	const HEAT_HOT_THRESHOLD = 'threshold_heathot';
	const POSITIVE_HYSTERESIS = 'positiveHysteresis';
	const HEATING_ACTIONS = 'heating';
	const COOLING_ACTIONS = 'cooling';
	const STOPPING_ACTIONS = 'stoping';
	const ORDER_CHANGE_ACTIONS = 'orderChange';
	const FAILURE_ACTIONS = 'failure';
	const FAILURE_ACTUATOR_ACTIONS = 'failureActuator';
	const WINDOW_ALERT_DELAY = 'window_alertIfOpenMoreThan';
	const MAX_TIME_UPDATE_TEMP = 'maxTimeUpdateTemp';
	const STOVE_BOILER = 'stove_boiler';
	const HEAT_FAILURE_OFFSET = 'offsetHeatFaillure';
	const COLD_FAILURE_OFFSET = 'offsetColdFaillure';
	const TEMPERATURE_INDOOR_MIN = 'temperature_indoor_min';
	const TEMPERATURE_INDOOR_MAX = 'temperature_indoor_max';

	private function __construct() {
	}
}
