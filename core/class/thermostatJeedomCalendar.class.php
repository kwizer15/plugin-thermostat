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

class thermostatJeedomCalendar implements thermostatCalendar {

	private $eqLogic;
	private $log;

	public function __construct($_eqLogic, thermostatLog $_log) {
		$this->eqLogic = $_eqLogic;
		$this->log = $_log;
	}

	public function available() {
		try {
			$plugin = plugin::byId('calendar');
			if (!is_object($plugin) || $plugin->isActive() != 1) {
				return false;
			}
		} catch (Exception $ex) {
			$this->log->debug(__('Plugin agenda non détecté', __FILE__));
			return false;
		}
		return class_exists('calendar_event');
	}

	public function nextEvent() {
		$thermostat = $this->eqLogic->getCmd(null, 'thermostat');
		$next = null;
		$position = null;
		foreach ($this->eqLogic->getCmd(null, 'modeAction', null, true) as $mode) {
			if(!is_object($mode)){
				continue;
			}
			$events = calendar_event::searchByCmd($mode->getId());
			if (is_array($events) && count($events) > 0) {
				foreach ($events as $event) {
					$calendar = $event->getEqLogic();
					$stateCalendar = $calendar->getCmd(null, 'state');
					if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
						continue;
					}
					foreach ($event->getCmd_param('start') as $action) {
						if ($action['cmd'] == '#' . $mode->getId() . '#') {
							$position = 'start';
						}
					}
					foreach ($event->getCmd_param('end') as $action) {
						if ($action['cmd'] == '#' . $mode->getId() . '#') {
							if ($position == 'start') {
								$position = null;
							} else {
								$position = 'end';
							}
						}
					}
					$nextOccurence = $event->nextOccurrence($position, true);
					if ($nextOccurence['date'] != '' && ($next == null || (strtotime($next['date']) > strtotime($nextOccurence['date']) && strtotime($nextOccurence['date']) > (strtotime('now') + 120)))) {
						$consigne = null;
						foreach ($this->eqLogic->getConfiguration('existingMode') as $existingMode) {
							if ($mode->getName() == $existingMode['name']) {
								foreach ($existingMode['actions'] as $action) {
									if ('#' . $thermostat->getId() . '#' == $action['cmd']) {
										$consigne = $action['options']['slider'];
									}
								}
							}
						}
						if ($consigne !== null) {
							$next = array(
								'date' => $nextOccurence['date'],
								'event' => $event,
								'consigne' => $consigne,
								'calendar_id' => $calendar->getId(),
								'cmd' => $mode->getId(),
								'type' => 'mode',
							);
						}
					}
				}
			}
		}
		$events = calendar_event::searchByCmd($thermostat->getId());
		if (is_array($events) && count($events) > 0) {
			foreach ($events as $event) {
				$calendar = $event->getEqLogic();
				$stateCalendar = $calendar->getCmd(null, 'state');
				if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
					continue;
				}
				foreach ($event->getCmd_param('start') as $action) {
					if ($action['cmd'] == '#' . $thermostat->getId() . '#') {
						$position = 'start';
						$options = $action['options'];
					}
				}
				foreach ($event->getCmd_param('end') as $action) {
					if ($action['cmd'] == '#' . $thermostat->getId() . '#') {
						if ($position == 'start') {
							$position = null;
						} else {
							$position = 'end';
							$options = $action['options'];
						}
					}
				}
				$nextOccurence = $event->nextOccurrence($position, true);
				if ($nextOccurence['date'] != '' && ($next == null || (strtotime($next['date']) > strtotime($nextOccurence['date']) && strtotime($nextOccurence['date']) > (strtotime('now') + 120)))) {
					$next = array(
						'date' => $nextOccurence['date'],
						'event' => $event,
						'calendar_id' => $calendar->getId(),
						'consigne' => $options['slider'],
						'type' => 'thermostat',
					);
				}
			}
		}
		return $next;
	}
	public function isInactive($_calendarId) {
		$calendar = calendar::byId($_calendarId);
		if (!is_object($calendar)) {
			return false;
		}
		$stateCalendar = $calendar->getCmd(null, 'state');
		return $calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1);
	}
}
