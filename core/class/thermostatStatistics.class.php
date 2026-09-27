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

class thermostatStatistics {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function updatePerformance() {
		$dju = $this->dju(date('Y-m-d'));
		if ($dju === null) {
			return;
		}
		$cmd = $this->thermostat->getCmd('info', 'performance');
		if (!is_object($cmd)) {
			return;
		}
		$performance = round(jeedom::evaluateExpression($this->thermostat->getConfiguration('consumption')) / $dju, 2);
		if ($performance <= 0) {
			return;
		}
		$cmd->event($performance);
	}

	public function runtimeByDay($_startDate = null, $_endDate = null) {
		$actifCmd = $this->thermostat->getCmd(null, 'actif');
		if (!is_object($actifCmd)) {
			return array();
		}
		$return = array();
		$prevValue = 0;
		$prevDatetime = 0;
		$day = strtotime($_startDate . ' 00:00:00 UTC');
		$endDatetime = strtotime($_endDate . ' 00:00:00 UTC');
		while ($day <= $endDatetime) {
			$return[date('Y-m-d', $day)] = array($day * 1000, 0);
			$day = $day + 3600 * 24;
		}
		foreach ($actifCmd->getHistory($_startDate, $_endDate) as $history) {
			if (date('Y-m-d', strtotime($history->getDatetime())) != $day && $prevValue == 1 && $day != null) {
				if (strtotime($day . ' 23:59:59') > $prevDatetime) {
					$return[$day][1] += (strtotime($day . ' 23:59:59') - $prevDatetime) / 60;
				}
				$prevDatetime = strtotime(date('Y-m-d 00:00:00', strtotime($history->getDatetime())));
			}
			$day = date('Y-m-d', strtotime($history->getDatetime()));
			if (!isset($return[$day])) {
				$return[$day] = array(strtotime($day . ' 00:00:00 UTC') * 1000, 0);
			}
			if ($history->getValue() == 1 && $prevValue == 0) {
				$prevDatetime = strtotime($history->getDatetime());
				$prevValue = 1;
			}
			if ($history->getValue() == 0 && $prevValue == 1) {
				if ($prevDatetime > 0 && strtotime($history->getDatetime()) > $prevDatetime) {
					$return[$day][1] += (strtotime($history->getDatetime()) - $prevDatetime) / 60;
				}
				$prevValue = 0;
			}
		}
		return $return;
	}

	public function dju($_date = null) {
		if ($_date == null) {
			$_date = date('Y-m-d');
		}
		$cmd = $this->thermostat->getCmd(null, 'temperature_outdoor');
		if (!is_object($cmd)) {
			return null;
		}
		$stats = $cmd->getStatistique($_date . ' 00:00:01', $_date . ' 23:59:59');
		if (!isset($stats['min']) || !isset($stats['max'])) {
			return null;
		}
		return 18 - (($stats['min'] + $stats['max']) / 2);
	}
}
