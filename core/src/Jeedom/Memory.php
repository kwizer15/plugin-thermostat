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

namespace Jeedom\Plugin\Thermostat\Jeedom;

use Jeedom\Plugin\Thermostat\Domain\Actuator\StateMemory;
use Jeedom\Plugin\Thermostat\Domain\Command\Memory as CommandMemory;
use Jeedom\Plugin\Thermostat\Domain\Engine\Memory as EngineMemory;
use Jeedom\Plugin\Thermostat\Domain\Learning\CycleMemory;
use Jeedom\Plugin\Thermostat\Domain\Power\Memory as PowerMemory;
use Jeedom\Plugin\Thermostat\Domain\SensorWatch\Memory as SensorWatchMemory;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\Memory as SmartStartMemory;
use Jeedom\Plugin\Thermostat\Domain\Window\Memory as WindowMemory;

class Memory implements PowerMemory, CycleMemory, SmartStartMemory, StateMemory, WindowMemory, EngineMemory, SensorWatchMemory, CommandMemory {

	/** @var \thermostat */
	private $eqLogic;

	public function __construct(\thermostat $_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function lastState() {
		return $this->eqLogic->getCache(CacheKey::LAST_STATE);
	}

	public function lastPower() {
		return $this->eqLogic->getCache(CacheKey::LAST_POWER, 0);
	}

	public function lastOrder() {
		return $this->eqLogic->getCache(CacheKey::LAST_ORDER, 0);
	}

	public function lastTempIn() {
		return $this->eqLogic->getCache(CacheKey::LAST_TEMP_IN, 0);
	}

	public function consecutiveFailures() {
		return $this->eqLogic->getCache(CacheKey::CONSECUTIVE_FAILURES, 0);
	}

	public function temperatureAlert() {
		return $this->eqLogic->getCache(CacheKey::TEMPERATURE_ALERT, 0);
	}

	public function setTemperatureAlert($_alert) {
		$this->eqLogic->setCache(CacheKey::TEMPERATURE_ALERT, $_alert);
	}

	public function smartStart() {
		return $this->eqLogic->getCache(CacheKey::SMART_START);
	}

	public function setSmartStart($_smartStart) {
		$this->eqLogic->setCache(CacheKey::SMART_START, $_smartStart);
	}
	public function setLastState($_state) {
		$this->eqLogic->setCache(CacheKey::LAST_STATE, $_state);
	}
	public function windowState($_cmdId) {
		return $this->eqLogic->getCache(CacheKey::WINDOW_STATE_PREFIX . $_cmdId, 0);
	}

	public function setWindowState($_cmdId, $_state) {
		$this->eqLogic->setCache(CacheKey::WINDOW_STATE_PREFIX . $_cmdId, $_state);
	}

	public function closedAt($_cmdId) {
		return $this->eqLogic->getCache(CacheKey::WINDOW_CLOSE_PREFIX . $_cmdId . CacheKey::WINDOW_CLOSE_DATETIME_SUFFIX);
	}

	public function setClosedAt($_cmdId, $_datetime) {
		$this->eqLogic->setCache(CacheKey::WINDOW_CLOSE_PREFIX . $_cmdId . CacheKey::WINDOW_CLOSE_DATETIME_SUFFIX, $_datetime);
	}

	public function openSince() {
		return $this->eqLogic->getCache(CacheKey::WINDOW_OPEN_SINCE, -1);
	}

	public function setOpenSince($_timestamp) {
		$this->eqLogic->setCache(CacheKey::WINDOW_OPEN_SINCE, $_timestamp);
	}

	public function alertSent() {
		return $this->eqLogic->getCache(CacheKey::WINDOW_ALERT_SENT, 0);
	}

	public function setAlertSent($_sent) {
		$this->eqLogic->setCache(CacheKey::WINDOW_ALERT_SENT, $_sent);
	}
	public function setLastOrder($_order) {
		$this->eqLogic->setCache(CacheKey::LAST_ORDER, $_order);
	}

	public function setLastTempIn($_temperature) {
		$this->eqLogic->setCache(CacheKey::LAST_TEMP_IN, $_temperature);
	}

	public function setLastTempOut($_temperature) {
		$this->eqLogic->setCache(CacheKey::LAST_TEMP_OUT, $_temperature);
	}

	public function setLastPower($_power) {
		$this->eqLogic->setCache(CacheKey::LAST_POWER, $_power);
	}

	public function setConsecutiveFailures($_count) {
		$this->eqLogic->setCache(CacheKey::CONSECUTIVE_FAILURES, $_count);
	}

	public function deltaOrder() {
		return $this->eqLogic->getCache(CacheKey::DELTA_ORDER, 0);
	}
	public function setDeltaOrder($_delta) {
		$this->eqLogic->setCache(CacheKey::DELTA_ORDER, $_delta);
	}
}
