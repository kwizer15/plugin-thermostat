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

namespace Jeedom\Plugin\Thermostat\Domain\Engine;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Cycle\Plan;
use Jeedom\Plugin\Thermostat\Domain\Cycle\Planner;
use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\Evaluator;
use Jeedom\Plugin\Thermostat\Domain\Learning\CoefficientLearner;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Persistence;
use Jeedom\Plugin\Thermostat\Domain\Power\Calculator;
use Jeedom\Plugin\Thermostat\Domain\Scheduling;
use Jeedom\Plugin\Thermostat\Domain\Sensors;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\SmartStart;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class TemporalEngine {

	/** @var Settings */
	private $settings;
	/** @var Memory */
	private $memory;
	/** @var Persistence */
	private $persistence;
	/** @var Evaluator */
	private $evaluator;
	/** @var Display */
	private $display;
	/** @var Sensors */
	private $sensors;
	/** @var Actuator */
	private $actuator;
	/** @var Scheduling */
	private $scheduler;
	/** @var Calculator */
	private $powerCalculator;
	/** @var SmartStart */
	private $smartStart;
	/** @var CoefficientLearner */
	private $coefficientLearner;
	/** @var Planner */
	private $cyclePlanner;
	/** @var Log */
	private $log;
	/** @var StatusLabels */
	private $labels;
	/** @var Translator */
	private $translator;

	public function __construct(Settings $_settings, Memory $_memory, Persistence $_persistence, Evaluator $_evaluator, Display $_display, Sensors $_sensors, Actuator $_actuator, Scheduling $_scheduler, Calculator $_powerCalculator, SmartStart $_smartStart, CoefficientLearner $_coefficientLearner, Planner $_cyclePlanner, Log $_log, StatusLabels $_labels, Translator $_translator) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->persistence = $_persistence;
		$this->evaluator = $_evaluator;
		$this->display = $_display;
		$this->sensors = $_sensors;
		$this->actuator = $_actuator;
		$this->scheduler = $_scheduler;
		$this->powerCalculator = $_powerCalculator;
		$this->smartStart = $_smartStart;
		$this->coefficientLearner = $_coefficientLearner;
		$this->cyclePlanner = $_cyclePlanner;
		$this->log = $_log;
		$this->labels = $_labels;
		$this->translator = $_translator;
	}

	/**
	 * @return void
	 */
	public function run() {
		$this->log->debug($this->translator->translate('{{Début calcul temporel}}'));
		$this->scheduler->reschedule(date('Y-m-d H:i:00', strtotime('+' . $this->settings->cycle() . ' min ' . date('Y-m-d H:i:00'))));
		$this->log->debug($this->translator->translate('{{Reprogrammation automatique : }}') . date('Y-m-d H:i:s', strtotime('+' . $this->settings->cycle() . ' ' . $this->translator->translate('{{minutes}}') . ' ' . date('Y-m-d H:i:00'))));
		$status = $this->display->status();
		if ($status == $this->labels->suspended()) {
			$this->log->debug($this->translator->translate('{{Thermostat suspendu}}'));
			return;
		}
		if ($this->settings->smartStartEnabled()) {
			$this->log->debug($this->translator->translate('{{Programmation Smartstart}}'));
			$this->smartStart->plan();
			$this->log->debug($this->translator->translate('{{Arrêt Smartstart}}'));
		}
		$mode = $this->display->mode();
		if ($mode == 'Off') {
			$this->log->debug($this->translator->translate('{{Thermostat sur off}}'));
			if ($status != $this->labels->stopped()) {
				$this->actuator->stop();
			}
			return;
		}
		$reading = $this->sensors->indoorReading();
		$temp_in = $reading->value();
		if ($reading->collectDate() != '' && $reading->collectDate() < date('Y-m-d H:i:s', strtotime('-' . $this->settings->maxTimeUpdateTemp() . ' minutes' . date('Y-m-d H:i:s')))) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->actuator->failure();
				$this->log->error($this->translator->translate("{{Attention il n'y a pas eu de mise à jour de la température depuis plus de}}") . ' ' . $this->settings->maxTimeUpdateTemp() . ' ' . $this->translator->translate('{{minutes}}') . ' (' . $reading->collectDate() . ')');
			}
			$this->log->debug($this->translator->translate("{{Je ne fais rien car il n'y a pas eu de mise a jour de la température depuis plus de}}") . ' ' . $this->settings->maxTimeUpdateTemp() . ' ' . $this->translator->translate('{{minutes}}'));
			$this->memory->setTemperatureAlert(1);
			$this->display->setStatus($this->labels->sensorFailure());
			return;
		}
		$temp_out = $this->sensors->outdoorTemperature();
		if (!is_numeric($temp_in)) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->log->error($this->translator->translate("{{La température intérieure n'est pas un numérique}}") . ' : ' . $temp_in);
			}
			$this->log->debug($this->translator->translate("{{Je ne fais rien car la température intérieure n'est pas un numérique}}"));
			$this->memory->setTemperatureAlert(1);
			$this->display->setStatus($this->labels->sensorFailure());
			return;
		}
		$this->memory->setTemperatureAlert(0);
		$this->smartStart->learn($temp_in);
		if (($temp_in < ($this->memory->lastOrder() - $this->settings->heatFailureOffset()) && $temp_in < $this->memory->lastTempIn() && $this->memory->lastState() == 'heat' && $this->settings->learnedCount('coeff_indoor_heat') > 25) ||
			($temp_in > ($this->memory->lastOrder() + $this->settings->coldFailureOffset()) && $temp_in > $this->memory->lastTempIn() && $this->memory->lastState() == 'cool' && $this->settings->learnedCount('coeff_indoor_cool') > 25)
		) {
			$this->memory->setConsecutiveFailures($this->memory->consecutiveFailures() + 1);
			if ($this->memory->consecutiveFailures() == 2) {
				$this->log->error($this->translator->translate('{{Attention une défaillance du chauffage est détectée}}'));
				$this->actuator->failureActuator();
			}
		} else {
			$this->memory->setConsecutiveFailures(0);
		}
		$this->coefficientLearner->learn($temp_in, $temp_out);
		$delta = $this->memory->deltaOrder();
		if ($delta > 0) {
			$this->log->debug($this->translator->translate('{{Delta consigne > 0}}') . ' (' . $delta . '), ' . $this->translator->translate('{{je lance le calcul avec consigne - delta/2}}'));
			$delta = $delta / 2;
		}
		$consigne = $this->display->setpoint();
		$temporal_data = $this->powerCalculator->compute(floatval($consigne) - $delta, $this->sensors->indoorTemperature(), $this->sensors->outdoorTemperature());
		if ($temporal_data['power'] > 0 && $delta > 0) {
			$this->log->debug($this->translator->translate('{{Power > 0 et delta consigne > 0}}') . ' (' . $delta . '), ' . $this->translator->translate('{{je relance le calcul avec consigne + delta/2}}'));
			$temporal_data = $this->powerCalculator->compute($consigne + $delta, $this->sensors->indoorTemperature(), $this->sensors->outdoorTemperature());
		}
		$this->memory->setLastPower($temporal_data['power']);
		$cycle = $this->evaluator->evaluate($this->settings->cycle());
		$plan = $this->cyclePlanner->plan($temporal_data['power'], $cycle, $this->memory->lastState() == 'heat', $this->settings->minCycleDuration(), $this->settings->stoveBoiler());
		$duration = $plan->duration();
		$this->memory->setLastOrder($consigne);
		$this->memory->setLastTempIn($temp_in);
		$this->memory->setLastTempOut($temp_out);
		$this->settings->setCycleEndDate(date('Y-m-d H:i:s', strtotime('+' . ceil($cycle * 0.9) . ' min ' . date('Y-m-d H:i:s'))));
		$this->log->debug($this->translator->translate('{{Durée du cycle}}') . '  : ' . $duration);
		if ($plan->isTooShort()) {
			$this->log->debug($this->translator->translate('{{Durée du cycle trop courte, aucun lancement}}'));
			$this->memory->setLastState('stop');
			$this->actuator->stop();
			$this->persistence->persist();
			return;
		}

		if ($plan->stop() == Plan::STOP_AFTER) {
			$this->scheduler->reschedule(date('Y-m-d H:i:s', strtotime('+' . $duration . ' min ' . date('Y-m-d H:i:s'))), true);
		} else if ($plan->stop() == Plan::STOP_CANCEL) {
			$this->scheduler->reschedule(null, true);
		}

		if ($this->memory->lastState() == 'heat' && $temporal_data['direction'] < 0) {
			$this->log->debug($this->translator->translate('{{Je dois refroidir mais avant je chauffais, je stop tout avant}}'));
			$this->memory->setLastState('stop');
			$this->actuator->stop();
			sleep(5);
		}else if ($this->memory->lastState() == 'cool' && $temporal_data['direction'] > 0) {
			$this->log->debug($this->translator->translate('{{Je dois chauffer mais avant je refroidissait, je stop tout avant}}'));
			$this->memory->setLastState('stop');
			$this->actuator->stop();
			sleep(5);
		}
		$this->persistence->persist();
		if ($duration > 0) {
			if ($temporal_data['direction'] > 0) {
				if ($this->actuator->heat()) {
					$this->display->setPower(round($temporal_data['power']));
				}
			} else {
				if ($this->actuator->cool()) {
					$this->display->setPower(round($temporal_data['power']));
				}
			}
		}
	}
}
