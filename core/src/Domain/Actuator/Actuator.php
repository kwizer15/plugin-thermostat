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

namespace Jeedom\Plugin\Thermostat\Domain\Actuator;

use Jeedom\Plugin\Thermostat\Domain\AllowMode;
use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\EngineRunner;
use Jeedom\Plugin\Thermostat\Domain\HeatingAction;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Persistence;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class Actuator {

	/** @var Settings */
	private $settings;
	/** @var StateMemory */
	private $memory;
	/** @var Persistence */
	private $persistence;
	/** @var Display */
	private $display;
	/** @var Actions */
	private $actions;
	/** @var EngineRunner */
	private $engine;
	/** @var Log */
	private $log;
	/** @var StatusLabels */
	private $labels;
	/** @var Translator */
	private $translator;

	public function __construct(Settings $_settings, StateMemory $_memory, Persistence $_persistence, Display $_display, Actions $_actions, EngineRunner $_engine, Log $_log, StatusLabels $_labels, Translator $_translator) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->persistence = $_persistence;
		$this->display = $_display;
		$this->actions = $_actions;
		$this->engine = $_engine;
		$this->log = $_log;
		$this->labels = $_labels;
		$this->translator = $_translator;
	}

	/**
	 * @param bool $_repeat
	 * @return bool
	 */
	public function heat($_repeat = false) {
		if (!$_repeat) {
			if ($this->display->isOff() || $this->display->status() == $this->labels->suspended()) {
				return false;
			}
			if ($this->settings->allowMode() != AllowMode::ALL && $this->settings->allowMode() != AllowMode::HEAT) {
				$this->stop();
				return false;
			}
			if (count($this->settings->heatingActions()) == 0) {
				$this->stop();
				return false;
			}
		}
		$this->display->setStatus($this->labels->heating());
		$this->log->debug($this->translator->translate('{{Action chauffage}}'));
		$this->actions->execute($this->settings->heatingActions(), true);
		if (!$_repeat) {
			$this->persistence->reload();
			$this->memory->setLastState(HeatingAction::HEAT);
			$this->display->setActive(1);
		}
		return true;
	}

	/**
	 * @param bool $_repeat
	 * @return bool
	 */
	public function cool($_repeat = false) {
		if (!$_repeat) {
			if ($this->display->isOff() || $this->display->status() == $this->labels->suspended()) {
				return false;
			}
			if ($this->settings->allowMode() != AllowMode::ALL && $this->settings->allowMode() != AllowMode::COOL) {
				$this->stop();
				return false;
			}
			if (count($this->settings->coolingActions()) == 0) {
				$this->stop();
				return false;
			}
		}
		$this->display->setStatus($this->labels->cooling());
		$this->log->debug($this->translator->translate('{{Action froid}}'));
		$this->actions->execute($this->settings->coolingActions(), true);
		if (!$_repeat) {
			$this->persistence->reload();
			$this->memory->setLastState(HeatingAction::COOL);
			$this->display->setActive(1);
		}
		return true;
	}

	/**
	 * @param bool $_repeat
	 * @param bool $_suspend
	 * @return void
	 */
	public function stop($_repeat = false, $_suspend = false) {
		if (!$_repeat && $this->display->status() == $this->labels->stopped()) {
			if ($this->display->power() > 0) {
				$_repeat = true;
			} else {
				return;
			}
		}
		$this->log->debug($this->translator->translate('{{Action stop}}'));
		$this->actions->execute($this->settings->stoppingActions(), true);
		$this->display->setPower(0);
		$this->display->setActive(0);

		if (!$_suspend) {
			$this->display->setStatus($this->labels->stopped());
		}
		if ($_repeat) {
			return;
		}
		$this->persistence->persist();
	}

	/**
	 * @return void
	 */
	public function orderChange() {
		if ($this->display->isOff() || $this->display->status() == $this->labels->suspended()) {
			return;
		}
		if (!is_array($this->settings->orderChangeActions()) || count($this->settings->orderChangeActions()) == 0) {
			return;
		}
		$this->actions->execute($this->settings->orderChangeActions(), true, array('modeChange' => true));
	}

	/**
	 * @return void
	 */
	public function failure() {
		if ($this->display->isOff() || $this->display->status() == $this->labels->suspended()) {
			return;
		}
		if (!is_array($this->settings->failureActions()) || count($this->settings->failureActions()) == 0) {
			return;
		}
		$this->log->debug($this->translator->translate('{{Action défaillance sonde}}'));
		$this->actions->execute($this->settings->failureActions(), false);
		$this->display->setStatus($this->labels->sensorFailure());
	}

	/**
	 * @return void
	 */
	public function failureActuator() {
		if ($this->display->isOff() || $this->display->status() == $this->labels->suspended()) {
			return;
		}
		if (!is_array($this->settings->failureActuatorActions()) || count($this->settings->failureActuatorActions()) == 0) {
			return;
		}
		$this->log->debug($this->translator->translate('{{Action défaillance chauffage}}'));
		$this->actions->execute($this->settings->failureActuatorActions(), false);
		$this->display->setStatus($this->labels->heatingFailure());
	}

	/**
	 * @param string $_name
	 * @return void
	 */
	public function executeMode($_name) {
		$thermostatCmd = false;
		$consigne = $this->display->setpoint();
		foreach ($this->settings->modes() as $existingMode) {
			if ($_name == $existingMode['name'] && $this->actions->applyMode($existingMode['actions'], $consigne)) {
				$thermostatCmd = true;
			}
		}
		$this->display->setMode($_name);
		if ($thermostatCmd == true) {
			$this->orderChange();
		}
		$this->engine->run();
	}

	/**
	 * @return void
	 */
	public function repeat() {
		switch ($this->display->status()) {
			case $this->labels->heating():
				$this->heat(true);
				break;
			case $this->labels->stopped():
				$this->stop(true);
				break;
			case $this->labels->cooling():
				$this->cool(true);
				break;
		}
	}
}
