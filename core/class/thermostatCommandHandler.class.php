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

class thermostatCommandHandler {

	private $settings;
	private $memory;
	private $persistence;
	private $display;
	private $actuator;
	private $engine;

	public function __construct(thermostatCommandSettings $_settings, thermostatCommandMemory $_memory, thermostatPersistence $_persistence, thermostatDisplay $_display, thermostatActuator $_actuator, thermostatEngineRunner $_engine) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->persistence = $_persistence;
		$this->display = $_display;
		$this->actuator = $_actuator;
		$this->engine = $_engine;
	}

	public function handle($_logicalId, $_name, $_options) {
		if ($_logicalId == 'deltaOrder') {
			$this->memory->setDeltaOrder($_options['slider']);
			return;
		} else if ($_logicalId == 'lock') {
			$this->display->lock();
		} else if ($_logicalId == 'unlock') {
			$this->display->unlock();
		} else if ($_logicalId == 'offset_heat' || $_logicalId == 'offset_cool') {
			if (is_numeric($_options['slider'])) {
				$this->settings->setOffset($_logicalId, $_options['slider']);
				$this->persistence->saveWithCommands();
			}
		} else if ($_logicalId == 'cool_only') {
			$this->allow('cool');
		} else if ($_logicalId == 'heat_only') {
			$this->allow('heat');
		} else if ($_logicalId == 'all_allow') {
			$this->allow('all');
		}
		if (!$this->display->hasLockState() || $this->display->locked()) {
			$this->display->refreshWidget();
			return;
		}
		if ($_logicalId == 'modeAction') {
			$this->actuator->executeMode($_name);
		} else if ($_logicalId == 'off') {
			$this->actuator->stop(false);
			$this->display->setMode(__('Off', __FILE__));
			$this->display->setStatus(__('Arrêté', __FILE__));
		} else if ($_logicalId == 'thermostat') {
			if (!isset($_options['slider']) || !is_numeric(str_replace(',', '.', $_options['slider']))) {
				return;
			}
			$changed = ($this->display->setpoint() != $_options['slider']);
			$this->display->setSetpoint($_options['slider']);
			if (!isset($_options['modeChange'])) {
				$this->display->setMode(__('Aucun', __FILE__));
			}
			if ($this->display->status() == __('Suspendu', __FILE__)) {
				return;
			}
			$this->actuator->orderChange();
			if ($changed) {
				$this->engine->run();
			}
		}
	}

	private function allow($_mode) {
		$this->settings->setAllowMode($_mode);
		$this->persistence->saveWithCommands();
		$this->engine->run();
	}
}
