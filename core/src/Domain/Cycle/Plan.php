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

class Plan {

	const STOP_AFTER = 'after';
	const STOP_CANCEL = 'cancel';
	const STOP_UNCHANGED = 'unchanged';

	/** @var float */
	private $duration;
	/** @var bool */
	private $tooShort;
	/** @var self::STOP_* */
	private $stop;

	/**
	 * @param float $_duration
	 * @param bool $_tooShort
	 * @param self::STOP_* $_stop
	 */
	public function __construct($_duration, $_tooShort, $_stop) {
		$this->duration = $_duration;
		$this->tooShort = $_tooShort;
		$this->stop = $_stop;
	}

	/**
	 * @return float
	 */
	public function duration() {
		return $this->duration;
	}

	/**
	 * @return bool
	 */
	public function isTooShort() {
		return $this->tooShort;
	}

	/**
	 * @return self::STOP_*
	 */
	public function stop() {
		return $this->stop;
	}
}
