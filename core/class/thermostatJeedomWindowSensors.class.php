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

class thermostatJeedomWindowSensors implements thermostatWindowSensors {

	public function name($_cmd) {
		return cmd::byString($_cmd)->getHumanName();
	}

	public function exists($_cmdId) {
		return is_object(cmd::byId($_cmdId));
	}

	public function read($_cmdId) {
		$cmd = cmd::byId($_cmdId);
		if (!is_object($cmd)) {
			return null;
		}
		$value = $cmd->execCmd();
		return new thermostatReading($value, $cmd->getCollectDate(), $cmd->getValueDate());
	}
}
