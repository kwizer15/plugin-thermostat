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

class thermostatPowerCalculator {

	private $thermostat;
	private $log;

	public function __construct($_thermostat, thermostatLog $_log) {
		$this->thermostat = $_thermostat;
		$this->log = $_log;
	}

	public function compute($_consigne, $_allowOverfull = false) {
		$temp_out = $this->thermostat->getCmd(null, 'temperature_outdoor')->execCmd();
		$temp_in = $this->thermostat->getCmd(null, 'temperature')->execCmd();
		if (!is_numeric($temp_out)) {
			$this->log->debug(__('Attention température extérieure erronée', __FILE__) . ' : ' . $temp_out);
			$temp_out = $_consigne;
		}

		$this->log->debug(__('Température intérieure', __FILE__) . ' : ' . $temp_in . ' - ' . __('Température extérieure', __FILE__) . ' : ' . $temp_out . ' - ' . __('Consigne', __FILE__) . ' : ' . $_consigne);
		$diff_in = $_consigne - $temp_in;
		$diff_out = $_consigne - $temp_out;
		$direction = ($_consigne > $temp_in) ? +1 : -1;
		if ($direction < 0 && (($temp_in < ($_consigne + 0.5) && $this->thermostat->getCache('lastState') == 'heat') || ($_consigne - $temp_out) > $this->thermostat->getConfiguration('direction::delta::heat', 0))) {
			$direction = +1;
		}
		if ($direction > 0 && (($temp_in > ($_consigne - 0.5) && $this->thermostat->getCache('lastState') == 'cool') || ($_consigne - $temp_out) < $this->thermostat->getConfiguration('direction::delta::cool', 0))) {
			$direction = -1;
		}
		$this->log->debug(__('Direction', __FILE__) . ' : ' . $direction);
		if ($temp_in >= ($_consigne + 1.5) && $direction == 1) {
			if ($this->thermostat->getCache('temp_threshold', 0) == 0) {
				$this->log->debug(__('La température est supérieure à la consigne de plus de 1.5°C, je ne fais rien', __FILE__));
			}
			$this->thermostat->setCache('temp_threshold', 1);
			return array('power' => 0, 'direction' => $direction);
		}
		if ($temp_in <= ($_consigne - 1.5) && $direction == -1) {
			if ($this->thermostat->getCache('temp_threshold', 0) == 0) {
				$this->log->debug(__('La température est inférieure à la consigne de plus de 1.5°C, je ne fais rien', __FILE__));
			}
			$this->thermostat->setCache('temp_threshold', 1);
			return array('power' => 0, 'direction' => $direction);
		}
		$this->thermostat->setCache('temp_threshold', 0);
		$coeff_out = ($direction > 0) ? $this->thermostat->getConfiguration('coeff_outdoor_heat') : $this->thermostat->getConfiguration('coeff_outdoor_cool');
		$coeff_in = ($direction > 0) ? $this->thermostat->getConfiguration('coeff_indoor_heat') : $this->thermostat->getConfiguration('coeff_indoor_cool');
		$offset = ($direction > 0) ? $this->thermostat->getConfiguration('offset_heat') : $this->thermostat->getConfiguration('offset_cool');
		$power = ($direction * $diff_in * $coeff_in) + ($direction * $diff_out * $coeff_out) + $offset;
		$this->log->debug('Power calcul : (' . $diff_in . ' * ' . $coeff_in . ') + (' . $diff_out . ' * ' . $coeff_out . ') + ' . $offset . ' = ' . $power);

		if (!$_allowOverfull && $this->thermostat->getConfiguration('offset_nextFullCyle') != '' && $this->thermostat->getConfiguration('offset_nextFullCyle') > 0 && $this->thermostat->getCache('last_power', 0) >= $this->thermostat->getConfiguration('threshold_heathot', 100)) {
			if ($this->thermostat->getCache('last_power', 0) >= 100) {
				$this->log->debug(__('Cycle précédent à 100%, applique offset', __FILE__) . ' : ' . $this->thermostat->getConfiguration('offset_nextFullCyle') . '%');
				$power -= $this->thermostat->getConfiguration('offset_nextFullCyle');
			} else {
				$this->log->debug(__('Cycle précédent à', __FILE__) . ' ' . $this->thermostat->getCache('last_power', 0) . '%, ' . __('applique offset', __FILE__) . ' : ' . $this->thermostat->getConfiguration('offset_nextFullCyle') . '%');
				$this->log->debug(__('Puissance de chauffe du cycle', __FILE__) . ' : ' . $power . '% - ' . $this->thermostat->getConfiguration('offset_nextFullCyle') . '% + ' . (100 - $this->thermostat->getCache('last_power', 0)) . '%');
				$power -= $this->thermostat->getConfiguration('offset_nextFullCyle') - (100 - $this->thermostat->getCache('last_power', 0));
			}
		}
		if ($power > 100 && !$_allowOverfull) {
			$power = 100;
		}
		if ($power < 0) {
			$power = 0;
		}
		return array('power' => $power, 'direction' => $direction);
	}
}
