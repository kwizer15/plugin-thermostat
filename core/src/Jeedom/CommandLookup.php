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

use Jeedom\Plugin\Thermostat\Domain\Translator;

class CommandLookup {

	/** @var \thermostat */
	private $eqLogic;
	/** @var Translator */
	private $translator;

	public function __construct(\thermostat $_eqLogic, Translator $_translator) {
		$this->eqLogic = $_eqLogic;
		$this->translator = $_translator;
	}

	/**
	 * @param string $_logicalId
	 */
	public function get($_logicalId): \cmd {
		$cmd = $this->eqLogic->getCmd(null, $_logicalId);
		if (!is_object($cmd)) {
			throw new MissingCommand($this->translator->translate('{{Commande introuvable}}') . ' : ' . $_logicalId);
		}
		return $cmd;
	}
}
