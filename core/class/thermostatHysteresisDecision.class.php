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

class thermostatHysteresisDecision {

	private $settings;
	private $log;

	public function __construct(thermostatHysteresisSettings $_settings, thermostatLog $_log) {
		$this->settings = $_settings;
		$this->log = $_log;
	}

	public function decide($_temp, $_consigne, $_status, $_lastState) {
		$allowMode = $this->settings->allowMode();
		$threshold = $this->settings->hysteresisThreshold();
		$positive = ($this->settings->positiveHysteresis() == 1);
		$hysteresis_low = ($allowMode == 'heat' && $positive) ? $_consigne : $_consigne - $threshold;
		$hysteresis_hight = ($allowMode == 'cool' && $positive) ? $_consigne : $_consigne + $threshold;
		$this->log->debug(__('Calcul', __FILE__) . ' => ' . __('consigne', __FILE__) . ' : ' . $_consigne . ' hysteresis_low : ' . $hysteresis_low . ' hysteresis_hight : ' . $hysteresis_hight . ' temp : ' . $_temp . ' ' . __('état précédent', __FILE__) . ' : ' . $_lastState);
		$action = 'none';
		if ($_temp < $hysteresis_low) {
			$action = 'heat';
		}
		if ($_temp > $hysteresis_hight) {
			$action = 'cool';
		}
		if ($action == 'heat' && $_lastState == 'cool' && ($_consigne - 2 * $threshold) < $_temp) {
			$action = 'none';
		}
		if ($action == 'cool' && $_lastState == 'heat' && ($_consigne + 2 * $threshold) > $_temp) {
			$action = 'none';
		}
		if ($_status == __('Chauffage', __FILE__) && $_temp > $hysteresis_hight) {
			$action = 'stop';
		}
		if ($_status == __('Climatisation', __FILE__) && $_temp < ($_consigne - $threshold)) {
			$action = 'stop';
		}
		if (($action == 'cool' || $action == 'heat') && $allowMode != 'all' && $allowMode != $action) {
			$action = 'none';
		}
		return $action;
	}
}
