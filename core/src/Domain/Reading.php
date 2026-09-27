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

namespace Jeedom\Plugin\Thermostat\Domain;

class Reading {

	/** @var mixed */
	private $value;
	/** @var string */
	private $collectDate;
	/** @var string */
	private $valueDate;

	/**
	 * @param mixed $_value
	 * @param string $_collectDate
	 * @param string $_valueDate
	 */
	public function __construct($_value, $_collectDate, $_valueDate) {
		$this->value = $_value;
		$this->collectDate = $_collectDate;
		$this->valueDate = $_valueDate;
	}

	/**
	 * @return mixed
	 */
	public function value() {
		return $this->value;
	}

	/**
	 * @return string
	 */
	public function collectDate() {
		return $this->collectDate;
	}

	/**
	 * @return string
	 */
	public function valueDate() {
		return $this->valueDate;
	}

	/**
	 * @param float|null $_maxMinutes
	 */
	public function isStale(Clock $_clock, $_maxMinutes): bool {
		return $_maxMinutes !== null && $this->collectDate != '' && strtotime($this->collectDate) < $_clock->now() - $_maxMinutes * 60;
	}
}
