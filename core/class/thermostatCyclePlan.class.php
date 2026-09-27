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

class thermostatCyclePlan {

	const STOP_AFTER = 'after';
	const STOP_CANCEL = 'cancel';
	const STOP_UNCHANGED = 'unchanged';

	private $duration;
	private $tooShort;
	private $stop;

	public function __construct($_duration, $_tooShort, $_stop) {
		$this->duration = $_duration;
		$this->tooShort = $_tooShort;
		$this->stop = $_stop;
	}

	public function duration() {
		return $this->duration;
	}

	public function isTooShort() {
		return $this->tooShort;
	}

	public function stop() {
		return $this->stop;
	}
}
