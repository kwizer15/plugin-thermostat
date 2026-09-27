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
	private $settings;
	private $memory;
	private $actuator;
	private $decision;
	private $log;

	public function __construct($_thermostat, thermostatEngineSettings $_settings, thermostatEngineMemory $_memory, thermostatActuator $_actuator, thermostatHysteresisDecision $_decision, thermostatLog $_log) {
		$this->thermostat = $_thermostat;
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->actuator = $_actuator;
		$this->decision = $_decision;
		$this->log = $_log;
	}

	public function run() {
		$this->log->debug(__("Lancement du calcul d'hystérésis", __FILE__));
		$status = $this->thermostat->getCmd(null, 'status')->execCmd();
		if ($status == __('Suspendu', __FILE__)) {
			$this->log->debug(__('Thermostat suspendu je ne fais rien', __FILE__));
			return;
		}
		if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__)) {
			$this->log->debug(__('Thermostat arrêté je ne fais rien', __FILE__));
			if ($status != __('Arrêté', __FILE__)) {
				$this->actuator->stop();
			}
			return;
		}
		$cmd = $this->thermostat->getCmd(null, 'temperature');
		$temp = $cmd->execCmd();
		if ($cmd->getCollectDate() != '' && $cmd->getCollectDate() < date('Y-m-d H:i:s', strtotime('-' . $this->settings->maxTimeUpdateTemp() . ' minutes' . date('Y-m-d H:i:s')))) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->actuator->failure();
				$this->log->error(__("Attention il n'y a pas eu de mise à jour de la température depuis plus de", __FILE__) . ' : ' . $this->settings->maxTimeUpdateTemp() . 'min (' . $cmd->getCollectDate() . ')');
			}
			$this->memory->setTemperatureAlert(1);
			$this->thermostat->getCmd(null, 'status')->event(__('Défaillance sonde', __FILE__));
			return;
		}
		$this->memory->setTemperatureAlert(0);
		$consigne = $this->thermostat->getCmd(null, 'order')->execCmd();
		$this->thermostat->getCmd(null, 'order')->addHistoryValue($consigne);
		$action = $this->decision->decide($temp, $consigne, $status, $this->memory->lastState());

		if ($action == 'heat') {
			if ($status != __('Chauffage', __FILE__)) {
				$this->log->debug(__('Je dois chauffer', __FILE__));
				$this->actuator->heat();
			}
		} else if ($action == 'cool') {
			if ($status != __('Climatisation', __FILE__)) {
				$this->log->debug(__('Je dois refroidir', __FILE__));
				$this->actuator->cool();
			}
		} else if ($action == 'stop') {
			if ($status != __('Arrêté', __FILE__)) {
				$this->log->debug(__("Je m'arrête", __FILE__));
				$this->actuator->stop();
			}
		}
	}
}
