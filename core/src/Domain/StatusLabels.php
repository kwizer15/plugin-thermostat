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

	/**
	 * @return string
	 */
	public function heating() {
		return $this->translator->translate('{{Chauffage}}');
	}

	/**
	 * @return string
	 */
	public function cooling() {
		return $this->translator->translate('{{Climatisation}}');
	}

	/**
	 * @return string
	 */
	public function stopped() {
		return $this->translator->translate('{{Arrêté}}');
	}

	/**
	 * @return string
	 */
	public function suspended() {
		return $this->translator->translate('{{Suspendu}}');
	}

	/**
	 * @return string
	 */
	public function computing() {
		return $this->translator->translate('{{Calcul}}');
	}

	/**
	 * @return string
	 */
	public function sensorFailure() {
		return $this->translator->translate('{{Défaillance sonde}}');
	}

	/**
	 * @return string
	 */
	public function heatingFailure() {
		return $this->translator->translate('{{Défaillance chauffage}}');
	}

	/**
	 * @return string
	 */
	public function off() {
		return $this->translator->translate('{{Off}}');
	}

	/**
	 * @return string
	 */
	public function none() {
		return $this->translator->translate('{{Aucun}}');
	}
}
