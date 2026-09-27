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

class thermostatConfiguration {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function apply() {
		if ($this->thermostat->getConfiguration('order_max') === '') {
			$this->thermostat->setConfiguration('order_max', 28);
		}
		if ($this->thermostat->getConfiguration('order_min') === '') {
			$this->thermostat->setConfiguration('order_min', 15);
		}
		if ($this->thermostat->getConfiguration('order_min') > $this->thermostat->getConfiguration('order_max')) {
			throw new Exception(__('La température de consigne minimale ne peut être supérieure à la consigne maximale', __FILE__));
		}
		if ($this->thermostat->getConfiguration('coeff_indoor_heat') === '') {
			$this->thermostat->setConfiguration('coeff_indoor_heat', 10);
		}
		if ($this->thermostat->getConfiguration('coeff_indoor_cool') === '') {
			$this->thermostat->setConfiguration('coeff_indoor_cool', 10);
		}
		if ($this->thermostat->getConfiguration('coeff_outdoor_heat') === '') {
			$this->thermostat->setConfiguration('coeff_outdoor_heat', 2);
		}
		if ($this->thermostat->getConfiguration('coeff_outdoor_cool') === '') {
			$this->thermostat->setConfiguration('coeff_outdoor_cool', 2);
		}
		if ($this->thermostat->getConfiguration('minCycleDuration') === '') {
			$this->thermostat->setConfiguration('minCycleDuration', 5);
		}
		if ($this->thermostat->getConfiguration('offset_heat') === '') {
			$this->thermostat->setConfiguration('offset_heat', 0);
		}
		if ($this->thermostat->getConfiguration('offset_cool') === '') {
			$this->thermostat->setConfiguration('offset_cool', 0);
		}
		if ($this->thermostat->getConfiguration('minCycleDuration') < 0 || $this->thermostat->getConfiguration('minCycleDuration') > 90) {
			throw new Exception(__('Le temps de chauffe minimal doit être compris entre 0% et 90%', __FILE__));
		}
		if ($this->thermostat->getConfiguration('cycle') === '') {
			$this->thermostat->setConfiguration('cycle', 59);
		}
		if ($this->thermostat->getConfiguration('smart_start') === '') {
			$this->thermostat->setConfiguration('smart_start', 1);
		}
		if ($this->thermostat->getConfiguration('cycle') < 15) {
			throw new Exception(__('Le temps de cycle doit être supérieur à 15 minutes', __FILE__));
		}
		if ($this->thermostat->getConfiguration('autolearn') === '') {
			$this->thermostat->setConfiguration('autolearn', 1);
		}
		if ($this->thermostat->getConfiguration('coeff_indoor_cool_autolearn') === '' || $this->thermostat->getConfiguration('coeff_indoor_cool_autolearn') < 1) {
			$this->thermostat->setConfiguration('coeff_indoor_cool_autolearn', 1);
		}
		if ($this->thermostat->getConfiguration('coeff_indoor_heat_autolearn') === '' || $this->thermostat->getConfiguration('coeff_indoor_heat_autolearn') < 1) {
			$this->thermostat->setConfiguration('coeff_indoor_heat_autolearn', 1);
		}
		if ($this->thermostat->getConfiguration('coeff_outdoor_heat_autolearn') === '' || $this->thermostat->getConfiguration('coeff_outdoor_heat_autolearn') < 1) {
			$this->thermostat->setConfiguration('coeff_outdoor_heat_autolearn', 0);
		}
		if ($this->thermostat->getConfiguration('coeff_outdoor_cool_autolearn') === '' || $this->thermostat->getConfiguration('coeff_outdoor_cool_autolearn') < 1) {
			$this->thermostat->setConfiguration('coeff_outdoor_cool_autolearn', 0);
		}
		if ($this->thermostat->getConfiguration('engine') == 'hysteresis') {
			$this->thermostat->setConfiguration('hysteresis_threshold', str_replace(',', '.', $this->thermostat->getConfiguration('hysteresis_threshold', 1)));
		}
		if (is_array($this->thermostat->getConfiguration('existingMode'))) {
			foreach ($this->thermostat->getConfiguration('existingMode') as $existingMode) {
				if (strtolower($existingMode['name']) == __('off', __FILE__)) {
					throw new Exception(__("Vous ne pouvez faire un mode s'appelant Off car une commande Off existe déjà", __FILE__));
				}
				if (strtolower($existingMode['name']) == __('status', __FILE__)) {
					throw new Exception(__("Vous ne pouvez faire un mode s'appelant Status car une commande Status existe déjà", __FILE__));
				}
				if (strtolower($existingMode['name']) == __('thermostat', __FILE__)) {
					throw new Exception(__("Vous ne pouvez faire un mode s'appelant Thermostat car une commande Thermostat existe déjà", __FILE__));
				}
			}
		}
		$this->thermostat->setCategory('heating', 1);
	}
}
