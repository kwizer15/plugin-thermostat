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

class thermostatJeedomMemory implements thermostatPowerMemory, thermostatCycleMemory, thermostatSmartStartMemory, thermostatStateMemory, thermostatWindowMemory, thermostatEngineMemory {

	private $eqLogic;

	public function __construct($_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function lastState() {
		return $this->eqLogic->getCache('lastState');
	}

	public function lastPower() {
		return $this->eqLogic->getCache('last_power', 0);
	}

	public function lastOrder() {
		return $this->eqLogic->getCache('lastOrder', 0);
	}

	public function lastTempIn() {
		return $this->eqLogic->getCache('lastTempIn', 0);
	}

	public function consecutiveFailures() {
		return $this->eqLogic->getCache('nbConsecutiveFaillure', 0);
	}

	public function temperatureAlert() {
		return $this->eqLogic->getCache('temp_threshold', 0);
	}

	public function setTemperatureAlert($_alert) {
		$this->eqLogic->setCache('temp_threshold', $_alert);
	}

	public function smartStart() {
		return $this->eqLogic->getCache('smartStart');
	}

	public function setSmartStart($_smartStart) {
		$this->eqLogic->setCache('smartStart', $_smartStart);
	}
	public function setLastState($_state) {
		$this->eqLogic->setCache('lastState', $_state);
	}
	public function windowState($_cmdId) {
		return $this->eqLogic->getCache('window::state::' . $_cmdId, 0);
	}

	public function setWindowState($_cmdId, $_state) {
		$this->eqLogic->setCache('window::state::' . $_cmdId, $_state);
	}

	public function closedAt($_cmdId) {
		return $this->eqLogic->getCache('window::close::' . $_cmdId . '::datetime');
	}

	public function setClosedAt($_cmdId, $_datetime) {
		$this->eqLogic->setCache('window::close::' . $_cmdId . '::datetime', $_datetime);
	}

	public function openSince() {
		return $this->eqLogic->getCache('window::state::open', -1);
	}

	public function setOpenSince($_timestamp) {
		$this->eqLogic->setCache('window::state::open', $_timestamp);
	}

	public function alertSent() {
		return $this->eqLogic->getCache('alertSendForWindow', 0);
	}

	public function setAlertSent($_sent) {
		$this->eqLogic->setCache('alertSendForWindow', $_sent);
	}
	public function setLastOrder($_order) {
		$this->eqLogic->setCache('lastOrder', $_order);
	}

	public function setLastTempIn($_temperature) {
		$this->eqLogic->setCache('lastTempIn', $_temperature);
	}

	public function setLastTempOut($_temperature) {
		$this->eqLogic->setCache('lastTempOut', $_temperature);
	}

	public function setLastPower($_power) {
		$this->eqLogic->setCache('last_power', $_power);
	}

	public function setConsecutiveFailures($_count) {
		$this->eqLogic->setCache('nbConsecutiveFaillure', $_count);
	}

	public function deltaOrder() {
		return $this->eqLogic->getCache('deltaOrder', 0);
	}
}
