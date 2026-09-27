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

	private $store;
	private $translator;

	public function __construct(thermostatConfigurationStore $_store, thermostatTranslator $_translator) {
		$this->store = $_store;
		$this->translator = $_translator;
	}

	public function apply() {
		if ($this->store->value('order_max') === '') {
			$this->store->change('order_max', 28);
		}
		if ($this->store->value('order_min') === '') {
			$this->store->change('order_min', 15);
		}
		if ($this->store->value('order_min') > $this->store->value('order_max')) {
			throw new Exception($this->translator->translate('{{La température de consigne minimale ne peut être supérieure à la consigne maximale}}'));
		}
		if ($this->store->value('coeff_indoor_heat') === '') {
			$this->store->change('coeff_indoor_heat', 10);
		}
		if ($this->store->value('coeff_indoor_cool') === '') {
			$this->store->change('coeff_indoor_cool', 10);
		}
		if ($this->store->value('coeff_outdoor_heat') === '') {
			$this->store->change('coeff_outdoor_heat', 2);
		}
		if ($this->store->value('coeff_outdoor_cool') === '') {
			$this->store->change('coeff_outdoor_cool', 2);
		}
		if ($this->store->value('minCycleDuration') === '') {
			$this->store->change('minCycleDuration', 5);
		}
		if ($this->store->value('offset_heat') === '') {
			$this->store->change('offset_heat', 0);
		}
		if ($this->store->value('offset_cool') === '') {
			$this->store->change('offset_cool', 0);
		}
		if ($this->store->value('minCycleDuration') < 0 || $this->store->value('minCycleDuration') > 90) {
			throw new Exception($this->translator->translate('{{Le temps de chauffe minimal doit être compris entre 0% et 90%}}'));
		}
		if ($this->store->value('cycle') === '') {
			$this->store->change('cycle', 59);
		}
		if ($this->store->value('smart_start') === '') {
			$this->store->change('smart_start', 1);
		}
		if ($this->store->value('cycle') < 15) {
			throw new Exception($this->translator->translate('{{Le temps de cycle doit être supérieur à 15 minutes}}'));
		}
		if ($this->store->value('autolearn') === '') {
			$this->store->change('autolearn', 1);
		}
		if ($this->store->value('coeff_indoor_cool_autolearn') === '' || $this->store->value('coeff_indoor_cool_autolearn') < 1) {
			$this->store->change('coeff_indoor_cool_autolearn', 1);
		}
		if ($this->store->value('coeff_indoor_heat_autolearn') === '' || $this->store->value('coeff_indoor_heat_autolearn') < 1) {
			$this->store->change('coeff_indoor_heat_autolearn', 1);
		}
		if ($this->store->value('coeff_outdoor_heat_autolearn') === '' || $this->store->value('coeff_outdoor_heat_autolearn') < 1) {
			$this->store->change('coeff_outdoor_heat_autolearn', 0);
		}
		if ($this->store->value('coeff_outdoor_cool_autolearn') === '' || $this->store->value('coeff_outdoor_cool_autolearn') < 1) {
			$this->store->change('coeff_outdoor_cool_autolearn', 0);
		}
		if ($this->store->value('engine') == 'hysteresis') {
			$this->store->change('hysteresis_threshold', str_replace(',', '.', $this->store->value('hysteresis_threshold', 1)));
		}
		if (is_array($this->store->value('existingMode'))) {
			foreach ($this->store->value('existingMode') as $existingMode) {
				if (strtolower($existingMode['name']) == $this->translator->translate('{{off}}')) {
					throw new Exception($this->translator->translate("{{Vous ne pouvez faire un mode s'appelant Off car une commande Off existe déjà}}"));
				}
				if (strtolower($existingMode['name']) == $this->translator->translate('{{status}}')) {
					throw new Exception($this->translator->translate("{{Vous ne pouvez faire un mode s'appelant Status car une commande Status existe déjà}}"));
				}
				if (strtolower($existingMode['name']) == $this->translator->translate('{{thermostat}}')) {
					throw new Exception($this->translator->translate("{{Vous ne pouvez faire un mode s'appelant Thermostat car une commande Thermostat existe déjà}}"));
				}
			}
		}
		$this->store->markAsHeating();
	}
}
