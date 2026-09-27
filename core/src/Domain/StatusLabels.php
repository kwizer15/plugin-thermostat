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

class StatusLabels {

	/** @var Translator */
	private $translator;

	public function __construct(Translator $_translator) {
		$this->translator = $_translator;
	}

	public function heating() {
		return $this->translator->translate('{{Chauffage}}');
	}

	public function cooling() {
		return $this->translator->translate('{{Climatisation}}');
	}

	public function stopped() {
		return $this->translator->translate('{{Arrêté}}');
	}

	public function suspended() {
		return $this->translator->translate('{{Suspendu}}');
	}

	public function computing() {
		return $this->translator->translate('{{Calcul}}');
	}

	public function sensorFailure() {
		return $this->translator->translate('{{Défaillance sonde}}');
	}

	public function heatingFailure() {
		return $this->translator->translate('{{Défaillance chauffage}}');
	}

	public function off() {
		return $this->translator->translate('{{Off}}');
	}

	public function none() {
		return $this->translator->translate('{{Aucun}}');
	}
}
