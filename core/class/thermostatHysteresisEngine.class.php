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

class thermostatHysteresisEngine {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	private function actuator() {
		return new thermostatActuator($this->thermostat);
	}

	public function run() {
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __("Lancement du calcul d'hystérésis", __FILE__));
		$status = $this->thermostat->getCmd(null, 'status')->execCmd();
		if ($status == __('Suspendu', __FILE__)) {
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Thermostat suspendu je ne fais rien', __FILE__));
			return;
		}
		if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__)) {
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Thermostat arrêté je ne fais rien', __FILE__));
			if ($status != __('Arrêté', __FILE__)) {
				$this->actuator()->stop();
			}
			return;
		}
		$cmd = $this->thermostat->getCmd(null, 'temperature');
		$temp = $cmd->execCmd();
		if ($cmd->getCollectDate() != '' && $cmd->getCollectDate() < date('Y-m-d H:i:s', strtotime('-' . $this->thermostat->getConfiguration('maxTimeUpdateTemp') . ' minutes' . date('Y-m-d H:i:s')))) {
			if ($this->thermostat->getCache('temp_threshold', 0) == 0) {
				$this->actuator()->failure();
				log::add('thermostat', 'error', $this->thermostat->getHumanName() . ' ' . __("Attention il n'y a pas eu de mise à jour de la température depuis plus de", __FILE__) . ' : ' . $this->thermostat->getConfiguration('maxTimeUpdateTemp') . 'min (' . $cmd->getCollectDate() . ')');
			}
			$this->thermostat->setCache('temp_threshold', 1);
			$this->thermostat->getCmd(null, 'status')->event(__('Défaillance sonde', __FILE__));
			return;
		}
		$this->thermostat->setCache('temp_threshold', 0);
		$consigne = $this->thermostat->getCmd(null, 'order')->execCmd();
		$this->thermostat->getCmd(null, 'order')->addHistoryValue($consigne);
		$hysteresis_low = ($this->thermostat->getConfiguration('allow_mode', 'all') == 'heat' && $this->thermostat->getConfiguration('positiveHysteresis', 0) == 1) ? $consigne : $consigne - $this->thermostat->getConfiguration('hysteresis_threshold', 1);
		$hysteresis_hight = ($this->thermostat->getConfiguration('allow_mode', 'all') == 'cool' && $this->thermostat->getConfiguration('positiveHysteresis', 0) == 1) ? $consigne : $consigne + $this->thermostat->getConfiguration('hysteresis_threshold', 1);
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Calcul', __FILE__) . ' => ' . __('consigne', __FILE__) . ' : ' . $consigne . ' hysteresis_low : ' . $hysteresis_low . ' hysteresis_hight : ' . $hysteresis_hight . ' temp : ' . $temp . ' ' . __('état précédent', __FILE__) . ' : ' . $this->thermostat->getCache('lastState'));
		$action = 'none';
		if ($temp < $hysteresis_low) {
			$action = 'heat';
		}
		if ($temp > $hysteresis_hight) {
			$action = 'cool';
		}
		if ($action == 'heat' && $this->thermostat->getCache('lastState') == 'cool' && ($consigne - 2 * $this->thermostat->getConfiguration('hysteresis_threshold', 1)) < $temp) {
			$action = 'none';
		}
		if ($action == 'cool' && $this->thermostat->getCache('lastState') == 'heat' && ($consigne + 2 * $this->thermostat->getConfiguration('hysteresis_threshold', 1)) > $temp) {
			$action = 'none';
		}
		if ($status == __('Chauffage', __FILE__) && $temp > $hysteresis_hight) {
			$action = 'stop';
		}
		if ($status == __('Climatisation', __FILE__) && $temp < ($consigne - $this->thermostat->getConfiguration('hysteresis_threshold', 1))) {
			$action = 'stop';
		}
		if (($action == 'cool' || $action == 'heat') && $this->thermostat->getConfiguration('allow_mode', 'all') != 'all' && $this->thermostat->getConfiguration('allow_mode', 'all') != $action) {
			$action = 'none';
		}

		if ($action == 'heat') {
			if ($status != __('Chauffage', __FILE__)) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Je dois chauffer', __FILE__));
				$this->actuator()->heat();
			}
		} else if ($action == 'cool') {
			if ($status != __('Climatisation', __FILE__)) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Je dois refroidir', __FILE__));
				$this->actuator()->cool();
			}
		} else if ($action == 'stop') {
			if ($status != __('Arrêté', __FILE__)) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __("Je m'arrête", __FILE__));
				$this->actuator()->stop();
			}
		}
	}

	public function cron() {
		if ($this->thermostat->getConfiguration('engine', 'temporal') == 'hysteresis' && $this->thermostat->getConfiguration('hysteresis_cron') != '') {
			try {
				$c = new Cron\CronExpression(checkAndFixCron($this->thermostat->getConfiguration('hysteresis_cron')), new Cron\FieldFactory);
				if ($c->isDue()) {
					$this->thermostat->getCmd(null, 'temperature')->event(jeedom::evaluateExpression($this->thermostat->getConfiguration('temperature_indoor')));
					thermostat::hysteresis(array('thermostat_id' => $this->thermostat->getId()));
				}
			} catch (Exception $e) {
				log::add('thermostat', 'error', $this->thermostat->getHumanName() . ' : ' . $e->getMessage());
			}
		}
	}
}
