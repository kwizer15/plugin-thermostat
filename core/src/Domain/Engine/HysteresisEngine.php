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

namespace Jeedom\Plugin\Thermostat\Domain\Engine;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Clock;
use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\HeatingAction;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Sensors;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class HysteresisEngine {

	/** @var Settings */
	private $settings;
	/** @var Memory */
	private $memory;
	/** @var Display */
	private $display;
	/** @var Sensors */
	private $sensors;
	/** @var Actuator */
	private $actuator;
	/** @var HysteresisDecision */
	private $decision;
	/** @var Log */
	private $log;
	/** @var StatusLabels */
	private $labels;
	/** @var Translator */
	private $translator;
	/** @var Clock */
	private $clock;

	public function __construct(Settings $_settings, Memory $_memory, Display $_display, Sensors $_sensors, Actuator $_actuator, HysteresisDecision $_decision, Log $_log, StatusLabels $_labels, Translator $_translator, Clock $_clock) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->display = $_display;
		$this->sensors = $_sensors;
		$this->actuator = $_actuator;
		$this->decision = $_decision;
		$this->log = $_log;
		$this->labels = $_labels;
		$this->translator = $_translator;
		$this->clock = $_clock;
	}

	/**
	 * @return void
	 */
	public function run() {
		$this->log->debug($this->translator->translate("{{Lancement du calcul d'hystérésis}}"));
		$status = $this->display->status();
		if ($status == $this->labels->suspended()) {
			$this->log->debug($this->translator->translate('{{Thermostat suspendu je ne fais rien}}'));
			return;
		}
		if ($this->display->isOff()) {
			$this->log->debug($this->translator->translate('{{Thermostat arrêté je ne fais rien}}'));
			if ($status != $this->labels->stopped()) {
				$this->actuator->stop();
			}
			return;
		}
		$reading = $this->sensors->indoorReading();
		$temp = $reading->value();
		if ($reading->isStale($this->clock, $this->settings->maxTimeUpdateTemp())) {
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

		if ($action == HeatingAction::HEAT) {
			if ($status != $this->labels->heating()) {
				$this->log->debug($this->translator->translate('{{Je dois chauffer}}'));
				$this->actuator->heat();
			}
		} else if ($action == HeatingAction::COOL) {
			if ($status != $this->labels->cooling()) {
				$this->log->debug($this->translator->translate('{{Je dois refroidir}}'));
				$this->actuator->cool();
			}
		} else if ($action == HeatingAction::STOP) {
			if ($status != $this->labels->stopped()) {
				$this->log->debug($this->translator->translate("{{Je m'arrête}}"));
				$this->actuator->stop();
			}
		}
	}
}
