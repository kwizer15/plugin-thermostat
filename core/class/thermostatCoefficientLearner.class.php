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

class thermostatCoefficientLearner {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function learn($_temp_in, $_temp_out) {
		if ($this->thermostat->getCache('nbConsecutiveFaillure', 0) < 3 && $this->thermostat->getConfiguration('autolearn') == 1 && strtotime($this->thermostat->getConfiguration('endDate')) < strtotime('now')) {
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Démarre auto-apprentissage', __FILE__));
			if ($this->thermostat->getCache('last_power', 0) < 100 && $this->thermostat->getCache('last_power', 0) > 0) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Last power ok, check what I have to learn, last state : ' . $this->thermostat->getCache('lastState'));
				if ($this->thermostat->getCache('lastState') == 'heat') {
					log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Last state is heat');
					if ($_temp_in > $this->thermostat->getCache('lastTempIn', 0) && $this->thermostat->getCache('lastOrder', 0) > $this->thermostat->getCache('lastTempIn', 0)) {
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Last temps in < at current temp in');
						$coeff = $this->learnCoefficient('coeff_indoor_heat', $this->thermostat->getConfiguration('coeff_indoor_heat') * (($this->thermostat->getCache('lastOrder', 0) - $this->thermostat->getCache('lastTempIn', 0)) / ($_temp_in - $this->thermostat->getCache('lastTempIn', 0))));
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' New coeff heat indoor : ' . $coeff);
					} else if ($_temp_out < $this->thermostat->getCache('lastOrder', 0)) {
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Learn outdoor heat');
						$coeff = $this->learnCoefficient('coeff_outdoor_heat', $this->thermostat->getConfiguration('coeff_indoor_heat') * (($this->thermostat->getCache('lastOrder', 0) - $_temp_in) / ($this->thermostat->getCache('lastOrder', 0) - $_temp_out)) + $this->thermostat->getConfiguration('coeff_outdoor_heat'));
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' New coeff outdoor heat: ' . $coeff);
					}
				}

				if ($this->thermostat->getCache('lastState') == 'cool') {
					log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Last state is cool');
					if ($_temp_in < $this->thermostat->getCache('lastTempIn', 0) && $this->thermostat->getCache('lastOrder', 0) < $this->thermostat->getCache('lastTempIn', 0)) {
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Last temps in > at current temp in');
						$coeff = $this->learnCoefficient('coeff_indoor_cool', $this->thermostat->getConfiguration('coeff_indoor_cool') * (($this->thermostat->getCache('lastTempIn', 0) - $this->thermostat->getCache('lastOrder', 0)) / ($this->thermostat->getCache('lastTempIn', 0) - $_temp_in)));
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' New coeff cool indoor : ' . $coeff);
					} else if ($_temp_out > $this->thermostat->getCache('lastOrder', 0)) {
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' Learn outdoor cool');
						$coeff = $this->learnCoefficient('coeff_outdoor_cool', $this->thermostat->getConfiguration('coeff_indoor_cool') * (($this->thermostat->getCache('lastOrder', 0) - $_temp_in) / ($this->thermostat->getCache('lastOrder', 0) - $_temp_out)) + $this->thermostat->getConfiguration('coeff_outdoor_cool'));
						log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' New coeff outdoor cool : ' . $coeff);
					}
				}
			}
		}
	}

	public function learnCoefficient($_key, $_measured) {
		$count = $this->thermostat->getConfiguration($_key . '_autolearn');
		$coeff = ($this->thermostat->getConfiguration($_key) * $count + $_measured) / ($count + 1);
		$this->thermostat->setConfiguration($_key . '_autolearn', min($count + 1, 50));
		if ($coeff < 0 || !is_numeric($coeff)) {
			$coeff = 0;
		}
		$this->thermostat->setConfiguration($_key, round($coeff, 2));
		$this->thermostat->checkAndUpdateCmd($_key, round($coeff, 2));
		return $coeff;
	}
}
