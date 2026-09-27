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

namespace Jeedom\Plugin\Thermostat\Domain\Command;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\EngineRunner;
use Jeedom\Plugin\Thermostat\Domain\Persistence;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;

class Handler {

	/** @var Settings */
	private $settings;
	/** @var Memory */
	private $memory;
	/** @var Persistence */
	private $persistence;
	/** @var Display */
	private $display;
	/** @var Actuator */
	private $actuator;
	/** @var EngineRunner */
	private $engine;
	/** @var StatusLabels */
	private $labels;

	public function __construct(Settings $_settings, Memory $_memory, Persistence $_persistence, Display $_display, Actuator $_actuator, EngineRunner $_engine, StatusLabels $_labels) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->persistence = $_persistence;
		$this->display = $_display;
		$this->actuator = $_actuator;
		$this->engine = $_engine;
		$this->labels = $_labels;
	}

	/**
	 * @param string $_logicalId
	 * @param string $_name
	 * @param array<string, mixed> $_options
	 * @return void
	 */
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
			$this->display->setMode($this->labels->off());
			$this->display->setStatus($this->labels->stopped());
		} else if ($_logicalId == 'thermostat') {
			if (!isset($_options['slider']) || !is_numeric(str_replace(',', '.', $_options['slider']))) {
				return;
			}
			$changed = ($this->display->setpoint() != $_options['slider']);
			$this->display->setSetpoint($_options['slider']);
			if (!isset($_options['modeChange'])) {
				$this->display->setMode($this->labels->none());
			}
			if ($this->display->status() == $this->labels->suspended()) {
				return;
			}
			$this->actuator->orderChange();
			if ($changed) {
				$this->engine->run();
			}
		}
	}

	/**
	 * @param string $_mode
	 * @return void
	 */
	private function allow($_mode) {
		$this->settings->setAllowMode($_mode);
		$this->persistence->saveWithCommands();
		$this->engine->run();
	}
}
