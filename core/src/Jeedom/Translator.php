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

use Jeedom\Plugin\Thermostat\Domain\Translator as DomainTranslator;

class Translator implements DomainTranslator {

	/** @var string */
	private $file;

	/**
	 * @param string $_file
	 */
	public function __construct($_file) {
		$this->file = $_file;
	}

	public function translate($_text) {
		return preg_replace_callback('/\{\{(.*?)\}\}/s', function ($_matches) {
			return __($_matches[1], $this->file);
		}, $_text);
	}
}
