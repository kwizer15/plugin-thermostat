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

namespace Jeedom\Plugin\Thermostat\Domain\SmartStart;

use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\Evaluator;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Power\Calculator;
use Jeedom\Plugin\Thermostat\Domain\Scheduling;
use Jeedom\Plugin\Thermostat\Domain\Sensors;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class SmartStart {

	private $settings;
	private $memory;
	private $calendar;
	private $sensors;
	private $display;
	private $controls;
	private $evaluator;
	private $powerCalculator;
	private $scheduler;
	private $log;
	private $translator;

	public function __construct(Settings $_settings, Memory $_memory, Calendar $_calendar, Sensors $_sensors, Display $_display, Controls $_controls, Evaluator $_evaluator, Calculator $_powerCalculator, Scheduling $_scheduler, Log $_log, Translator $_translator) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->calendar = $_calendar;
		$this->sensors = $_sensors;
		$this->display = $_display;
		$this->controls = $_controls;
		$this->evaluator = $_evaluator;
		$this->powerCalculator = $_powerCalculator;
		$this->scheduler = $_scheduler;
		$this->log = $_log;
		$this->translator = $_translator;
	}

	public function plan() {
		if ($this->settings->engine() != 'temporal') {
			return '';
		}
		if (!$this->calendar->available()) {
			return '';
		}
		$this->log->debug($this->translator->translate('{{Plugin agenda détecté}}'));
		$next = $this->calendar->nextEvent();
		if ($next == null || $next['date'] == '') {
			$this->log->debug($this->translator->translate('{{Smartstart : aucun événement trouvé}}'));
			return '';
		}
		$cycle = $this->evaluator->evaluate($this->settings->cycle());
		if ($next['date'] != '' && strtotime($next['date']) > strtotime(date('Y-m-d H:i:s'))) {
			$temporal_data = $this->powerCalculator->compute($this->evaluator->evaluate($next['consigne']), $this->sensors->indoorTemperature(), $this->sensors->outdoorTemperature(), true);
			if ($temporal_data['power'] < 0) {
				$this->log->debug($this->translator->translate('{{Smartstart non pris en compte car power < 0 }}') . ' ' . $temporal_data['power']);
				return;
			}
			$duration = round(($temporal_data['power'] * $cycle) / 100 * $this->settings->anticipationFactor());
			if ($duration < 5) {
				$this->log->debug($this->translator->translate('{{Smartstart non pris en compte car la durée}}') . ' ' . $duration);
				return '';
			}
			$next['schedule'] = date('Y-m-d H:i:s', strtotime('-' . $duration . ' min ' . $next['date']));
			$this->log->debug($this->translator->translate('{{Durée Smartstart}}') . ' : ' . $duration . ' ' . $this->translator->translate('{{à}}') . ' ' . $next['date'] . ' ' . $this->translator->translate('{{programmation}}') . ' : ' . $next['schedule']);
			if (strtotime($next['schedule']) > (strtotime('now') + 120)) {
				$this->log->debug($this->translator->translate('{{Prochain Smartstart}}') . ' : ' . $next['schedule']);
				$this->scheduler->reschedule($next['schedule'], false, $next);
			}
		}
	}

	public function trigger($_options) {
		if (isset($_options['next']) && isset($_options['next']['calendar_id']) && $this->calendar->isInactive($_options['next']['calendar_id'])) {
			return;
		}
		if (!$this->settings->smartStartEnabled()) {
			return;
		}
		$this->log->debug($this->translator->translate('{{Next info}}') . ' : ' . print_r($_options['next'], true));
		if ($this->display->locked()) {
			$this->log->debug($this->translator->translate('{{Thermostat verrouillé je ne fais rien}}'));
		} else if ($_options['next']['type'] == 'thermostat') {
			$this->log->debug($this->translator->translate('{{Type thermostat envoi de la consigne}}') . ' : ' . $_options['next']['consigne']);
			$this->remember($_options['next']);
			$this->controls->requestSetpoint($_options['next']['consigne']);
		} else if ($_options['next']['type'] == 'mode' && isset($_options['next']['cmd']) && $this->controls->modeExists($_options['next']['cmd'])) {
			$this->log->debug($this->translator->translate('{{Type mode envoi de la commande}}') . ' : ' . $_options['next']['cmd']);
			$this->remember($_options['next']);
			$this->controls->runMode($_options['next']['cmd']);
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
			$this->log->debug($this->translator->translate('{{Smartstart : pas d\'apprentissage}}') . ' (' . $needed . ' / ' . $achieved . ')');
			return;
		}
		$factor = $this->settings->anticipationFactor();
		$count = $this->settings->anticipationCount();
		$target = $factor * min(max($needed / $achieved, 0.5), 2);
		$factor = min(max(($factor * $count + $target) / ($count + 1), 0.5), 3);
		$this->settings->storeAnticipation(round($factor, 2), min($count + 1, 10));
		$this->log->debug($this->translator->translate('{{Smartstart : nouvelle anticipation}}') . ' : ' . round($factor, 2) . ' (' . $achieved . '/' . $needed . '°C)');
	}
}
