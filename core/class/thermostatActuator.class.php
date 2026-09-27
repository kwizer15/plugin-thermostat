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

class thermostatActuator {

	private $settings;
	private $memory;
	private $persistence;
	private $display;
	private $actions;
	private $engine;
	private $log;

	public function __construct(thermostatActuatorSettings $_settings, thermostatStateMemory $_memory, thermostatPersistence $_persistence, thermostatDisplay $_display, thermostatActions $_actions, thermostatEngineRunner $_engine, thermostatLog $_log) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->persistence = $_persistence;
		$this->display = $_display;
		$this->actions = $_actions;
		$this->engine = $_engine;
		$this->log = $_log;
	}

	public function heat($_repeat = false) {
		if (!$_repeat) {
			if ($this->display->mode() == __('Off', __FILE__) || $this->display->status() == __('Suspendu', __FILE__)) {
				return false;
			}
			if ($this->settings->allowMode() != 'all' && $this->settings->allowMode() != 'heat') {
				$this->stop();
				return false;
			}
			if (count($this->settings->heatingActions()) == 0) {
				$this->stop();
				return false;
			}
		}
		$this->display->setStatus(__('Chauffage', __FILE__));
		$this->log->debug(__('Action chauffage', __FILE__));
		$this->actions->execute($this->settings->heatingActions(), true);
		if (!$_repeat) {
			$this->persistence->reload();
			$this->memory->setLastState('heat');
			$this->display->setActive(1);
		}
		return true;
	}

	public function cool($_repeat = false) {
		if (!$_repeat) {
			if ($this->display->mode() == __('Off', __FILE__) || $this->display->status() == __('Suspendu', __FILE__)) {
				return false;
			}
			if ($this->settings->allowMode() != 'all' && $this->settings->allowMode() != 'cool') {
				$this->stop();
				return false;
			}
			if (count($this->settings->coolingActions()) == 0) {
				$this->stop();
				return false;
			}
		}
		$this->display->setStatus(__('Climatisation', __FILE__));
		$this->log->debug(__('Action froid', __FILE__));
		$this->actions->execute($this->settings->coolingActions(), true);
		if (!$_repeat) {
			$this->persistence->reload();
			$this->memory->setLastState('cool');
			$this->display->setActive(1);
		}
		return true;
	}

	public function stop($_repeat = false, $_suspend = false) {
		if (!$_repeat && $this->display->status() == __('Arrêté', __FILE__)) {
			if ($this->display->power() > 0) {
				$_repeat = true;
			} else {
				return;
			}
		}
		$this->log->debug(__('Action stop', __FILE__));
		$this->actions->execute($this->settings->stoppingActions(), true);
		$this->display->setPower(0);
		$this->display->setActive(0);

		if (!$_suspend) {
			$this->display->setStatus(__('Arrêté', __FILE__));
		}
		if ($_repeat) {
			return;
		}
		$this->persistence->persist();
	}

	public function orderChange() {
		if ($this->display->mode() == __('Off', __FILE__) || $this->display->status() == __('Suspendu', __FILE__)) {
			return;
		}
		if (!is_array($this->settings->orderChangeActions()) || count($this->settings->orderChangeActions()) == 0) {
			return;
		}
		$this->actions->execute($this->settings->orderChangeActions(), true, array('modeChange' => true));
	}

	public function failure() {
		if ($this->display->mode() == __('Off', __FILE__) || $this->display->status() == __('Suspendu', __FILE__)) {
			return;
		}
		if (!is_array($this->settings->failureActions()) || count($this->settings->failureActions()) == 0) {
			return;
		}
		$this->log->debug(__('Action défaillance sonde', __FILE__));
		$this->actions->execute($this->settings->failureActions(), false);
		$this->display->setStatus(__('Défaillance sonde', __FILE__));
	}

	public function failureActuator() {
		if ($this->display->mode() == __('Off', __FILE__) || $this->display->status() == __('Suspendu', __FILE__)) {
			return;
		}
		if (!is_array($this->settings->failureActuatorActions()) || count($this->settings->failureActuatorActions()) == 0) {
			return;
		}
		$this->log->debug(__('Action défaillance chauffage', __FILE__));
		$this->actions->execute($this->settings->failureActuatorActions(), false);
		$this->display->setStatus(__('Défaillance chauffage', __FILE__));
	}

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

	public function repeat() {
		switch ($this->display->status()) {
			case __('Chauffage', __FILE__):
				$this->heat(true);
				break;
			case __('Arrêté', __FILE__):
				$this->stop(true);
				break;
			case __('Climatisation', __FILE__):
				$this->cool(true);
				break;
		}
	}
}
