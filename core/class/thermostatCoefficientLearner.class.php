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

	private $settings;
	private $memory;
	private $log;

	public function __construct(thermostatLearningSettings $_settings, thermostatCycleMemory $_memory, thermostatLog $_log) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->log = $_log;
	}

	public function learn($_temp_in, $_temp_out) {
		if ($this->memory->consecutiveFailures() >= 3 || $this->settings->autolearn() != 1 || strtotime($this->settings->cycleEndDate()) >= strtotime('now')) {
			return;
		}
		$this->log->debug(__('Démarre auto-apprentissage', __FILE__));
		$lastPower = $this->memory->lastPower();
		if ($lastPower >= 100 || $lastPower <= 0) {
			return;
		}
		$lastState = $this->memory->lastState();
		$lastOrder = $this->memory->lastOrder();
		$lastTempIn = $this->memory->lastTempIn();
		$this->log->debug('Last power ok, check what I have to learn, last state : ' . $lastState);
		if ($lastState == 'heat') {
			$this->log->debug('Last state is heat');
			if ($_temp_in > $lastTempIn && $lastOrder > $lastTempIn) {
				$this->log->debug('Last temps in < at current temp in');
				$coeff = $this->learnCoefficient('coeff_indoor_heat', $this->settings->coefficient('coeff_indoor_heat') * (($lastOrder - $lastTempIn) / ($_temp_in - $lastTempIn)));
				$this->log->debug('New coeff heat indoor : ' . $coeff);
			} else if ($_temp_out < $lastOrder) {
				$this->log->debug('Learn outdoor heat');
				$coeff = $this->learnCoefficient('coeff_outdoor_heat', $this->settings->coefficient('coeff_indoor_heat') * (($lastOrder - $_temp_in) / ($lastOrder - $_temp_out)) + $this->settings->coefficient('coeff_outdoor_heat'));
				$this->log->debug('New coeff outdoor heat: ' . $coeff);
			}
		}
		if ($lastState == 'cool') {
			$this->log->debug('Last state is cool');
			if ($_temp_in < $lastTempIn && $lastOrder < $lastTempIn) {
				$this->log->debug('Last temps in > at current temp in');
				$coeff = $this->learnCoefficient('coeff_indoor_cool', $this->settings->coefficient('coeff_indoor_cool') * (($lastTempIn - $lastOrder) / ($lastTempIn - $_temp_in)));
				$this->log->debug('New coeff cool indoor : ' . $coeff);
			} else if ($_temp_out > $lastOrder) {
				$this->log->debug('Learn outdoor cool');
				$coeff = $this->learnCoefficient('coeff_outdoor_cool', $this->settings->coefficient('coeff_indoor_cool') * (($lastOrder - $_temp_in) / ($lastOrder - $_temp_out)) + $this->settings->coefficient('coeff_outdoor_cool'));
				$this->log->debug('New coeff outdoor cool : ' . $coeff);
			}
		}
	}

	public function learnCoefficient($_key, $_measured) {
		$count = $this->settings->learnedCount($_key);
		$coeff = ($this->settings->coefficient($_key) * $count + $_measured) / ($count + 1);
		if ($coeff < 0 || !is_numeric($coeff)) {
			$coeff = 0;
		}
		$this->settings->storeCoefficient($_key, round($coeff, 2), min($count + 1, 50));
		return $coeff;
	}
}
