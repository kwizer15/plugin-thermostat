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

final class Value {

	/**
	 * @param mixed $_value
	 * @param float $_default
	 */
	public static function number($_value, $_default): float {
		$number = self::optionalNumber($_value);
		return ($number === null) ? $_default : $number;
	}

	/**
	 * @param mixed $_value
	 * @return float|null
	 */
	public static function optionalNumber($_value) {
		if (is_string($_value)) {
			$_value = str_replace(',', '.', $_value);
		}
		return is_numeric($_value) ? floatval($_value) : null;
	}

	/**
	 * @param mixed $_value
	 * @param int $_default
	 */
	public static function integer($_value, $_default): int {
		$number = self::optionalNumber($_value);
		return ($number === null) ? $_default : intval($number);
	}

	/**
	 * @param mixed $_value
	 */
	public static function flag($_value): bool {
		return $_value == 1;
	}

	private function __construct() {
	}
}
