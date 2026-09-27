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

	private $settings;
	private $memory;
	private $display;
	private $sensors;
	private $actuator;
	private $decision;
	private $log;
	private $labels;
	private $translator;

	public function __construct(thermostatEngineSettings $_settings, thermostatEngineMemory $_memory, thermostatDisplay $_display, thermostatSensors $_sensors, thermostatActuator $_actuator, thermostatHysteresisDecision $_decision, thermostatLog $_log, thermostatStatusLabels $_labels, thermostatTranslator $_translator) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->display = $_display;
		$this->sensors = $_sensors;
		$this->actuator = $_actuator;
		$this->decision = $_decision;
		$this->log = $_log;
		$this->labels = $_labels;
		$this->translator = $_translator;
	}

	public function run() {
		$this->log->debug($this->translator->translate("{{Lancement du calcul d'hystérésis}}"));
		$status = $this->display->status();
		if ($status == $this->labels->suspended()) {
			$this->log->debug($this->translator->translate('{{Thermostat suspendu je ne fais rien}}'));
			return;
		}
		if ($this->display->mode() == $this->labels->off()) {
			$this->log->debug($this->translator->translate('{{Thermostat arrêté je ne fais rien}}'));
			if ($status != $this->labels->stopped()) {
				$this->actuator->stop();
			}
			return;
		}
		$reading = $this->sensors->indoorReading();
		$temp = $reading->value();
		if ($reading->collectDate() != '' && $reading->collectDate() < date('Y-m-d H:i:s', strtotime('-' . $this->settings->maxTimeUpdateTemp() . ' minutes' . date('Y-m-d H:i:s')))) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->actuator->failure();
				$this->log->error($this->translator->translate("{{Attention il n'y a pas eu de mise à jour de la température depuis plus de}}") . ' : ' . $this->settings->maxTimeUpdateTemp() . 'min (' . $reading->collectDate() . ')');
			}
			$this->memory->setTemperatureAlert(1);
			$this->display->setStatus($this->labels->sensorFailure());
			return;
		}
		$this->memory->setTemperatureAlert(0);
		$consigne = $this->display->setpoint();
		$this->display->historizeSetpoint($consigne);
		$action = $this->decision->decide($temp, $consigne, $status, $this->memory->lastState());

		if ($action == 'heat') {
			if ($status != $this->labels->heating()) {
				$this->log->debug($this->translator->translate('{{Je dois chauffer}}'));
				$this->actuator->heat();
			}
		} else if ($action == 'cool') {
			if ($status != $this->labels->cooling()) {
				$this->log->debug($this->translator->translate('{{Je dois refroidir}}'));
				$this->actuator->cool();
			}
		} else if ($action == 'stop') {
			if ($status != $this->labels->stopped()) {
				$this->log->debug($this->translator->translate("{{Je m'arrête}}"));
				$this->actuator->stop();
			}
		}
	}
}
