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

use Jeedom\Plugin\Thermostat\Domain\Command\LogicalId;
use Jeedom\Plugin\Thermostat\Domain\Display as DomainDisplay;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;

class Display implements DomainDisplay {

	/** @var \thermostat */
	private $eqLogic;
	/** @var CommandLookup */
	private $commands;
	/** @var StatusLabels */
	private $labels;

	public function __construct(\thermostat $_eqLogic, CommandLookup $_commands, StatusLabels $_labels) {
		$this->eqLogic = $_eqLogic;
		$this->commands = $_commands;
		$this->labels = $_labels;
	}

	public function status() {
		return $this->commands->get(LogicalId::STATUS)->execCmd();
	}

	public function setStatus($_status) {
		$this->commands->get(LogicalId::STATUS)->event($_status);
	}

	public function mode() {
		return $this->commands->get(LogicalId::MODE)->execCmd();
	}

	public function setMode($_mode) {
		$this->commands->get(LogicalId::MODE)->event($_mode);
	}

	public function isOff() {
		return $this->mode() == $this->labels->off();
	}

	public function setpoint() {
		return $this->commands->get(LogicalId::ORDER)->execCmd();
	}

	public function setSetpoint($_value) {
		$this->commands->get(LogicalId::ORDER)->event($_value);
	}

	public function historizeSetpoint($_value) {
		$this->commands->get(LogicalId::ORDER)->addHistoryValue($_value);
	}

	public function setActive($_active) {
		$this->commands->get(LogicalId::ACTIVE)->event($_active);
	}

	public function power() {
		$power = $this->eqLogic->getCmd(null, LogicalId::POWER);
		return is_object($power) ? $power->execCmd() : null;
	}

	public function setPower($_power) {
		$power = $this->eqLogic->getCmd(null, LogicalId::POWER);
		if (is_object($power)) {
			$power->event($_power);
		}
	}
	public function locked() {
		$lockState = $this->eqLogic->getCmd(null, LogicalId::LOCK_STATE);
		return is_object($lockState) && $lockState->execCmd() == 1;
	}
	public function hasLockState() {
		return is_object($this->eqLogic->getCmd(null, LogicalId::LOCK_STATE));
	}

	public function lock() {
		$this->commands->get(LogicalId::LOCK_STATE)->event(1);
	}

	public function unlock() {
		$this->commands->get(LogicalId::LOCK_STATE)->event(0);
	}

	public function refreshWidget() {
		$this->eqLogic->refreshWidget();
	}
}
