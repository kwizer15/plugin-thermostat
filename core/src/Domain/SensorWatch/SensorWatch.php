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

namespace Jeedom\Plugin\Thermostat\Domain\SensorWatch;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Sensors;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class SensorWatch {

	private $settings;
	private $memory;
	private $display;
	private $sensors;
	private $actuator;
	private $log;
	private $translator;

	public function __construct(Settings $_settings, Memory $_memory, Display $_display, Sensors $_sensors, Actuator $_actuator, Log $_log, Translator $_translator) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->display = $_display;
		$this->sensors = $_sensors;
		$this->actuator = $_actuator;
		$this->log = $_log;
		$this->translator = $_translator;
	}

	public function check() {
		if (strtolower($this->display->mode()) == 'off') {
			return;
		}
		$reading = $this->sensors->indoorReading();
		$temp_in = $reading->value();
		$failure = false;
		if ($this->settings->maxTimeUpdateTemp() != '') {
			if ($reading->collectDate() != '' && strtotime($reading->collectDate()) < strtotime('-' . $this->settings->maxTimeUpdateTemp() . ' minutes' . date('Y-m-d H:i:s'))) {
				if ($this->memory->temperatureAlert() == 0) {
					$this->actuator->failure();
					$this->log->error($this->translator->translate("{{Attention il n'y a pas eu de mise à jour de la température depuis plus de}}") . ' : ' . $this->settings->maxTimeUpdateTemp() . ' ' . $this->translator->translate('{{minutes}}') . ' (' . $reading->collectDate() . ')');
				}
				$failure = true;
			}
		}
		if ($this->settings->indoorMinimum() != '' && is_numeric($this->settings->indoorMinimum()) && $this->settings->indoorMinimum() > $temp_in && $temp_in !== '') {
			if ($this->memory->temperatureAlert() == 0) {
				$this->actuator->failure();
				$this->log->error($this->translator->translate('{{Attention la température intérieure est en dessous du seuil autorisé}}') . ' : ' . $temp_in);
			}
			$failure = true;
		}
		if ($this->settings->indoorMaximum() != '' && is_numeric($this->settings->indoorMaximum()) && $this->settings->indoorMaximum() < $temp_in && $temp_in !== '') {
			if ($this->memory->temperatureAlert() == 0) {
				$this->actuator->failure();
				$this->log->error($this->translator->translate('{{Attention la température intérieure est au dessus du seuil autorisé}}') . ' : ' . $temp_in);
			}
			$failure = true;
		}
		if (!$failure) {
			$this->memory->setTemperatureAlert(0);
		} else {
			$this->memory->setTemperatureAlert(1);
		}
	}
}
