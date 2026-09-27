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

use Jeedom\Plugin\Thermostat\Domain\Log as DomainLog;

class Log implements DomainLog {

	/** @var string */
	private $name;

	/**
	 * @param string $_name
	 */
	public function __construct($_name) {
		$this->name = $_name;
	}

	public function debug($_message) {
		\log::add('thermostat', 'debug', $this->name . ' ' . $_message);
	}

	public function error($_message) {
		\log::add('thermostat', 'error', $this->name . ' ' . $_message);
	}
}
