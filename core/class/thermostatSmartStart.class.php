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

	private $settings;
	private $memory;
	private $calendar;
	private $sensors;
	private $evaluator;
	private $powerCalculator;
	private $scheduler;
	private $log;

	public function __construct(thermostatSmartStartSettings $_settings, thermostatSmartStartMemory $_memory, thermostatCalendar $_calendar, thermostatSensors $_sensors, thermostatEvaluator $_evaluator, thermostatPowerCalculator $_powerCalculator, thermostatScheduling $_scheduler, thermostatLog $_log) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->calendar = $_calendar;
		$this->sensors = $_sensors;
		$this->evaluator = $_evaluator;
		$this->powerCalculator = $_powerCalculator;
		$this->scheduler = $_scheduler;
		$this->log = $_log;
	}

	public function plan() {
		if ($this->settings->engine() != 'temporal') {
			return '';
		}
		if (!$this->calendar->available()) {
			return '';
		}
		$this->log->debug(__('Plugin agenda détecté', __FILE__));
		$next = $this->calendar->nextEvent();
		if ($next == null || $next['date'] == '') {
			$this->log->debug(__('Smartstart : aucun événement trouvé', __FILE__));
			return '';
		}
		$cycle = $this->evaluator->evaluate($this->settings->cycle());
		if ($next['date'] != '' && strtotime($next['date']) > strtotime(date('Y-m-d H:i:s'))) {
			$temporal_data = $this->powerCalculator->compute($this->evaluator->evaluate($next['consigne']), $this->sensors->indoorTemperature(), $this->sensors->outdoorTemperature(), true);
			if ($temporal_data['power'] < 0) {
				$this->log->debug(__('Smartstart non pris en compte car power < 0 ', __FILE__) . ' ' . $temporal_data['power']);
				return;
			}
			$duration = round(($temporal_data['power'] * $cycle) / 100 * $this->settings->anticipationFactor());
			if ($duration < 5) {
				$this->log->debug(__('Smartstart non pris en compte car la durée', __FILE__) . ' ' . $duration);
				return '';
			}
			$next['schedule'] = date('Y-m-d H:i:s', strtotime('-' . $duration . ' min ' . $next['date']));
			$this->log->debug(__('Durée Smartstart', __FILE__) . ' : ' . $duration . ' ' . __('à', __FILE__) . ' ' . $next['date'] . ' ' . __('programmation', __FILE__) . ' : ' . $next['schedule']);
			if (strtotime($next['schedule']) > (strtotime('now') + 120)) {
				$this->log->debug(__('Prochain Smartstart', __FILE__) . ' : ' . $next['schedule']);
				$this->scheduler->reschedule($next['schedule'], false, $next);
			}
		}
	}

	public function remember($_next) {
		$this->memory->setSmartStart(array(
			'start' => date('Y-m-d H:i:s'),
			'date' => $_next['date'],
			'consigne' => $this->evaluator->evaluate($_next['consigne']),
			'temperature' => $this->sensors->indoorTemperature(),
		));
	}

	public function learn($_temperature) {
		$smartStart = $this->memory->smartStart();
		if (!is_array($smartStart)) {
			return;
		}
		$eventTime = strtotime($smartStart['date']);
		if (strtotime('now') < $eventTime - 60) {
			return;
		}
		$this->memory->setSmartStart(null);
		if (strtotime('now') > $eventTime + 7200) {
			return;
		}
		$needed = $smartStart['consigne'] - $smartStart['temperature'];
		$achieved = $_temperature - $smartStart['temperature'];
		if ($needed < 0.5 || $achieved <= 0) {
			$this->log->debug(__('Smartstart : pas d\'apprentissage', __FILE__) . ' (' . $needed . ' / ' . $achieved . ')');
			return;
		}
		$factor = $this->settings->anticipationFactor();
		$count = $this->settings->anticipationCount();
		$target = $factor * min(max($needed / $achieved, 0.5), 2);
		$factor = min(max(($factor * $count + $target) / ($count + 1), 0.5), 3);
		$this->settings->storeAnticipation(round($factor, 2), min($count + 1, 10));
		$this->log->debug(__('Smartstart : nouvelle anticipation', __FILE__) . ' : ' . round($factor, 2) . ' (' . $achieved . '/' . $needed . '°C)');
	}
}
