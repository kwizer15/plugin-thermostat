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

use Jeedom\Plugin\Thermostat\Domain\Display as DomainDisplay;

class Display implements DomainDisplay {

	private $eqLogic;

	public function __construct($_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function status() {
		return $this->eqLogic->getCmd(null, 'status')->execCmd();
	}

	public function setStatus($_status) {
		$this->eqLogic->getCmd(null, 'status')->event($_status);
	}

	public function mode() {
		return $this->eqLogic->getCmd(null, 'mode')->execCmd();
	}

	public function setMode($_mode) {
		$this->eqLogic->getCmd(null, 'mode')->event($_mode);
	}

	public function setpoint() {
		return $this->eqLogic->getCmd(null, 'order')->execCmd();
	}

	public function setSetpoint($_value) {
		$this->eqLogic->getCmd(null, 'order')->event($_value);
	}

	public function historizeSetpoint($_value) {
		$this->eqLogic->getCmd(null, 'order')->addHistoryValue($_value);
	}

	public function setActive($_active) {
		$this->eqLogic->getCmd(null, 'actif')->event($_active);
	}

	public function power() {
		$power = $this->eqLogic->getCmd(null, 'power');
		return is_object($power) ? $power->execCmd() : null;
	}

	public function setPower($_power) {
		$power = $this->eqLogic->getCmd(null, 'power');
		if (is_object($power)) {
			$power->event($_power);
		}
	}
	public function locked() {
		$lockState = $this->eqLogic->getCmd(null, 'lock_state');
		return is_object($lockState) && $lockState->execCmd() == 1;
	}
	public function hasLockState() {
		return is_object($this->eqLogic->getCmd(null, 'lock_state'));
	}

	public function lock() {
		$this->eqLogic->getCmd(null, 'lock_state')->event(1);
	}

	public function unlock() {
		$this->eqLogic->getCmd(null, 'lock_state')->event(0);
	}

	public function refreshWidget() {
		$this->eqLogic->refreshWidget();
	}
}
