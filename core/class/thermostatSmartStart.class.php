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

class thermostatSmartStart {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function plan() {
		if ($this->thermostat->getConfiguration('engine', 'temporal') != 'temporal') {
			return '';
		}
		try {
			$plugin = plugin::byId('calendar');
			if (!is_object($plugin) || $plugin->isActive() != 1) {
				return '';
			}
		} catch (Exception $ex) {
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Plugin agenda non détecté', __FILE__));
			return '';
		}
		if (!class_exists('calendar_event')) {
			return '';
		}
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Plugin agenda détecté', __FILE__));

		$thermostat = $this->thermostat->getCmd(null, 'thermostat');
		$next = null;
		$position = null;
		foreach ($this->thermostat->getCmd(null, 'modeAction', null, true) as $mode) {
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
						foreach ($this->thermostat->getConfiguration('existingMode') as $existingMode) {
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
		if ($next == null || $next['date'] == '') {
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Smartstart : aucun événement trouvé', __FILE__));
			return '';
		}
		$cycle = jeedom::evaluateExpression($this->thermostat->getConfiguration('cycle'));
		if ($next['date'] != '' && strtotime($next['date']) > strtotime(date('Y-m-d H:i:s'))) {
			$temporal_data = (new thermostatPowerCalculator($this->thermostat))->compute(jeedom::evaluateExpression($next['consigne']), true);
			if ($temporal_data['power'] < 0) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Smartstart non pris en compte car power < 0 ', __FILE__) . ' ' . $temporal_data['power']);
				return;
			}
			$duration = round(($temporal_data['power'] * $cycle) / 100 * $this->thermostat->getConfiguration('smart_start_factor', 1));
			if ($duration < 5) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Smartstart non pris en compte car la durée', __FILE__) . ' ' . $duration);
				return '';
			}
			$next['schedule'] = date('Y-m-d H:i:s', strtotime('-' . $duration . ' min ' . $next['date']));
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Durée Smartstart', __FILE__) . ' : ' . $duration . ' ' . __('à', __FILE__) . ' ' . $next['date'] . ' ' . __('programmation', __FILE__) . ' : ' . $next['schedule']);
			if (strtotime($next['schedule']) > (strtotime('now') + 120)) {
				log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Prochain Smartstart', __FILE__) . ' : ' . $next['schedule']);
				(new thermostatScheduler($this->thermostat))->reschedule($next['schedule'], false, $next);
			}
		}
	}

	public function remember($_next) {
		$this->thermostat->setCache('smartStart', array(
			'start' => date('Y-m-d H:i:s'),
			'date' => $_next['date'],
			'consigne' => jeedom::evaluateExpression($_next['consigne']),
			'temperature' => $this->thermostat->getCmd(null, 'temperature')->execCmd(),
		));
	}

	public function learn($_temperature) {
		$smartStart = $this->thermostat->getCache('smartStart');
		if (!is_array($smartStart)) {
			return;
		}
		$eventTime = strtotime($smartStart['date']);
		if (strtotime('now') < $eventTime - 60) {
			return;
		}
		$this->thermostat->setCache('smartStart', null);
		if (strtotime('now') > $eventTime + 7200) {
			return;
		}
		$needed = $smartStart['consigne'] - $smartStart['temperature'];
		$achieved = $_temperature - $smartStart['temperature'];
		if ($needed < 0.5 || $achieved <= 0) {
			log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Smartstart : pas d\'apprentissage', __FILE__) . ' (' . $needed . ' / ' . $achieved . ')');
			return;
		}
		$factor = $this->thermostat->getConfiguration('smart_start_factor', 1);
		$count = $this->thermostat->getConfiguration('smart_start_autolearn', 0);
		$target = $factor * min(max($needed / $achieved, 0.5), 2);
		$factor = min(max(($factor * $count + $target) / ($count + 1), 0.5), 3);
		$this->thermostat->setConfiguration('smart_start_factor', round($factor, 2));
		$this->thermostat->setConfiguration('smart_start_autolearn', min($count + 1, 10));
		$this->thermostat->checkAndUpdateCmd('smart_start_factor', round($factor, 2));
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Smartstart : nouvelle anticipation', __FILE__) . ' : ' . round($factor, 2) . ' (' . $achieved . '/' . $needed . '°C)');
	}
}
