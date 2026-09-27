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

namespace Jeedom\Plugin\Thermostat\Domain\Configuration;

use Jeedom\Plugin\Thermostat\Domain\Engine\EngineType;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class Configuration {

	/** @var Store */
	private $store;
	/** @var Translator */
	private $translator;

	public function __construct(Store $_store, Translator $_translator) {
		$this->store = $_store;
		$this->translator = $_translator;
	}

	/**
	 * @return void
	 */
	public function apply() {
		if ($this->store->value(Key::ORDER_MAX) === '') {
			$this->store->change(Key::ORDER_MAX, 28);
		}
		if ($this->store->value(Key::ORDER_MIN) === '') {
			$this->store->change(Key::ORDER_MIN, 15);
		}
		if ($this->store->value(Key::ORDER_MIN) > $this->store->value(Key::ORDER_MAX)) {
			throw new \Exception($this->translator->translate('{{La température de consigne minimale ne peut être supérieure à la consigne maximale}}'));
		}
		if ($this->store->value(Key::COEFF_INDOOR_HEAT) === '') {
			$this->store->change(Key::COEFF_INDOOR_HEAT, 10);
		}
		if ($this->store->value(Key::COEFF_INDOOR_COOL) === '') {
			$this->store->change(Key::COEFF_INDOOR_COOL, 10);
		}
		if ($this->store->value(Key::COEFF_OUTDOOR_HEAT) === '') {
			$this->store->change(Key::COEFF_OUTDOOR_HEAT, 2);
		}
		if ($this->store->value(Key::COEFF_OUTDOOR_COOL) === '') {
			$this->store->change(Key::COEFF_OUTDOOR_COOL, 2);
		}
		if ($this->store->value(Key::MIN_CYCLE_DURATION) === '') {
			$this->store->change(Key::MIN_CYCLE_DURATION, 5);
		}
		if ($this->store->value(Key::OFFSET_HEAT) === '') {
			$this->store->change(Key::OFFSET_HEAT, 0);
		}
		if ($this->store->value(Key::OFFSET_COOL) === '') {
			$this->store->change(Key::OFFSET_COOL, 0);
		}
		if ($this->store->value(Key::MIN_CYCLE_DURATION) < 0 || $this->store->value(Key::MIN_CYCLE_DURATION) > 90) {
			throw new \Exception($this->translator->translate('{{Le temps de chauffe minimal doit être compris entre 0% et 90%}}'));
		}
		if ($this->store->value(Key::CYCLE) === '') {
			$this->store->change(Key::CYCLE, 59);
		}
		if ($this->store->value(Key::SMART_START) === '') {
			$this->store->change(Key::SMART_START, 1);
		}
		if ($this->store->value(Key::CYCLE) < 15) {
			throw new \Exception($this->translator->translate('{{Le temps de cycle doit être supérieur à 15 minutes}}'));
		}
		if ($this->store->value(Key::AUTOLEARN) === '') {
			$this->store->change(Key::AUTOLEARN, 1);
		}
		if ($this->store->value(Key::COEFF_INDOOR_COOL_AUTOLEARN) === '' || $this->store->value(Key::COEFF_INDOOR_COOL_AUTOLEARN) < 1) {
			$this->store->change(Key::COEFF_INDOOR_COOL_AUTOLEARN, 1);
		}
		if ($this->store->value(Key::COEFF_INDOOR_HEAT_AUTOLEARN) === '' || $this->store->value(Key::COEFF_INDOOR_HEAT_AUTOLEARN) < 1) {
			$this->store->change(Key::COEFF_INDOOR_HEAT_AUTOLEARN, 1);
		}
		if ($this->store->value(Key::COEFF_OUTDOOR_HEAT_AUTOLEARN) === '' || $this->store->value(Key::COEFF_OUTDOOR_HEAT_AUTOLEARN) < 1) {
			$this->store->change(Key::COEFF_OUTDOOR_HEAT_AUTOLEARN, 0);
		}
		if ($this->store->value(Key::COEFF_OUTDOOR_COOL_AUTOLEARN) === '' || $this->store->value(Key::COEFF_OUTDOOR_COOL_AUTOLEARN) < 1) {
			$this->store->change(Key::COEFF_OUTDOOR_COOL_AUTOLEARN, 0);
		}
		if ($this->store->value(Key::ENGINE) == EngineType::HYSTERESIS) {
			$this->store->change(Key::HYSTERESIS_THRESHOLD, str_replace(',', '.', $this->store->value(Key::HYSTERESIS_THRESHOLD, 1)));
		}
		if (is_array($this->store->value(Key::MODES))) {
			foreach ($this->store->value(Key::MODES) as $existingMode) {
				if (strtolower($existingMode['name']) == $this->translator->translate('{{off}}')) {
					throw new \Exception($this->translator->translate("{{Vous ne pouvez faire un mode s'appelant Off car une commande Off existe déjà}}"));
				}
				if (strtolower($existingMode['name']) == $this->translator->translate('{{status}}')) {
					throw new \Exception($this->translator->translate("{{Vous ne pouvez faire un mode s'appelant Status car une commande Status existe déjà}}"));
				}
				if (strtolower($existingMode['name']) == $this->translator->translate('{{thermostat}}')) {
					throw new \Exception($this->translator->translate("{{Vous ne pouvez faire un mode s'appelant Thermostat car une commande Thermostat existe déjà}}"));
				}
			}
		}
		$this->store->markAsHeating();
	}
}
