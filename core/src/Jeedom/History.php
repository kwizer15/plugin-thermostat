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

use Jeedom\Plugin\Thermostat\Domain\Statistics\History as StatisticsHistory;

class History implements StatisticsHistory {

	/** @var \thermostat */
	private $eqLogic;

	public function __construct(\thermostat $_eqLogic) {
		$this->eqLogic = $_eqLogic;
	}

	public function outdoorStatistics($_start, $_end) {
		$cmd = $this->eqLogic->getCmd(null, 'temperature_outdoor');
		if (!is_object($cmd)) {
			return null;
		}
		return $cmd->getStatistique($_start, $_end);
	}

	public function activeHistory($_start, $_end) {
		$cmd = $this->eqLogic->getCmd(null, 'actif');
		if (!is_object($cmd)) {
			return null;
		}
		$return = array();
		foreach ($cmd->getHistory($_start, $_end) as $history) {
			$return[] = array('datetime' => $history->getDatetime(), 'value' => $history->getValue());
		}
		return $return;
	}

	public function hasPerformance() {
		return is_object($this->eqLogic->getCmd('info', 'performance'));
	}

	public function publishPerformance($_performance) {
		$this->eqLogic->getCmd('info', 'performance')->event($_performance);
	}
}
