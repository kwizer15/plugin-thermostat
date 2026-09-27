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

class thermostatTemporalEngine {

	private $settings;
	private $memory;
	private $persistence;
	private $evaluator;
	private $display;
	private $sensors;
	private $actuator;
	private $scheduler;
	private $powerCalculator;
	private $smartStart;
	private $coefficientLearner;
	private $cyclePlanner;
	private $log;

	public function __construct(thermostatEngineSettings $_settings, thermostatEngineMemory $_memory, thermostatPersistence $_persistence, thermostatEvaluator $_evaluator, thermostatDisplay $_display, thermostatSensors $_sensors, thermostatActuator $_actuator, thermostatScheduling $_scheduler, thermostatPowerCalculator $_powerCalculator, thermostatSmartStart $_smartStart, thermostatCoefficientLearner $_coefficientLearner, thermostatCyclePlanner $_cyclePlanner, thermostatLog $_log) {
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
	}

	public function run() {
		$this->log->debug(__('Début calcul temporel', __FILE__));
		$this->scheduler->reschedule(date('Y-m-d H:i:00', strtotime('+' . $this->settings->cycle() . ' min ' . date('Y-m-d H:i:00'))));
		$this->log->debug(__('Reprogrammation automatique : ', __FILE__) . date('Y-m-d H:i:s', strtotime('+' . $this->settings->cycle() . ' ' . __('minutes', __FILE__) . ' ' . date('Y-m-d H:i:00'))));
		$status = $this->display->status();
		if ($status == __('Suspendu', __FILE__)) {
			$this->log->debug(__('Thermostat suspendu', __FILE__));
			return;
		}
		if ($this->settings->smartStartEnabled()) {
			$this->log->debug(__('Programmation Smartstart', __FILE__));
			$this->smartStart->plan();
			$this->log->debug(__('Arrêt Smartstart', __FILE__));
		}
		$mode = $this->display->mode();
		if ($mode == 'Off') {
			$this->log->debug(__('Thermostat sur off', __FILE__));
			if ($status != __('Arrêté', __FILE__)) {
				$this->actuator->stop();
			}
			return;
		}
		$reading = $this->sensors->indoorReading();
		$temp_in = $reading->value();
		if ($reading->collectDate() != '' && $reading->collectDate() < date('Y-m-d H:i:s', strtotime('-' . $this->settings->maxTimeUpdateTemp() . ' minutes' . date('Y-m-d H:i:s')))) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->actuator->failure();
				$this->log->error(__("Attention il n'y a pas eu de mise à jour de la température depuis plus de", __FILE__) . ' ' . $this->settings->maxTimeUpdateTemp() . ' ' . __('minutes', __FILE__) . ' (' . $reading->collectDate() . ')');
			}
			$this->log->debug(__("Je ne fais rien car il n'y a pas eu de mise a jour de la température depuis plus de", __FILE__) . ' ' . $this->settings->maxTimeUpdateTemp() . ' ' . __('minutes', __FILE__));
			$this->memory->setTemperatureAlert(1);
			$this->display->setStatus(__('Défaillance sonde', __FILE__));
			return;
		}
		$temp_out = $this->sensors->outdoorTemperature();
		if (!is_numeric($temp_in)) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->log->error(__("La température intérieure n'est pas un numérique", __FILE__) . ' : ' . $temp_in);
			}
			$this->log->debug(__("Je ne fais rien car la température intérieure n'est pas un numérique", __FILE__));
			$this->memory->setTemperatureAlert(1);
			$this->display->setStatus(__('Défaillance sonde', __FILE__));
			return;
		}
		$this->memory->setTemperatureAlert(0);
		$this->smartStart->learn($temp_in);
		if (($temp_in < ($this->memory->lastOrder() - $this->settings->heatFailureOffset()) && $temp_in < $this->memory->lastTempIn() && $this->memory->lastState() == 'heat' && $this->settings->learnedCount('coeff_indoor_heat') > 25) ||
			($temp_in > ($this->memory->lastOrder() + $this->settings->coldFailureOffset()) && $temp_in > $this->memory->lastTempIn() && $this->memory->lastState() == 'cool' && $this->settings->learnedCount('coeff_indoor_cool') > 25)
		) {
			$this->memory->setConsecutiveFailures($this->memory->consecutiveFailures() + 1);
			if ($this->memory->consecutiveFailures() == 2) {
				$this->log->error(__('Attention une défaillance du chauffage est détectée', __FILE__));
				$this->actuator->failureActuator();
			}
		} else {
			$this->memory->setConsecutiveFailures(0);
		}
		$this->coefficientLearner->learn($temp_in, $temp_out);
		$delta = $this->memory->deltaOrder();
		if ($delta > 0) {
			$this->log->debug(__('Delta consigne > 0', __FILE__) . ' (' . $delta . '), ' . __('je lance le calcul avec consigne - delta/2', __FILE__));
			$delta = $delta / 2;
		}
		$consigne = $this->display->setpoint();
		$temporal_data = $this->powerCalculator->compute(floatval($consigne) - $delta, $this->sensors->indoorTemperature(), $this->sensors->outdoorTemperature());
		if ($temporal_data['power'] > 0 && $delta > 0) {
			$this->log->debug(__('Power > 0 et delta consigne > 0', __FILE__) . ' (' . $delta . '), ' . __('je relance le calcul avec consigne + delta/2', __FILE__));
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
		$this->log->debug(__('Durée du cycle', __FILE__) . '  : ' . $duration);
		if ($plan->isTooShort()) {
			$this->log->debug(__('Durée du cycle trop courte, aucun lancement', __FILE__));
			$this->memory->setLastState('stop');
			$this->actuator->stop();
			$this->persistence->persist();
			return;
		}

		if ($plan->stop() == thermostatCyclePlan::STOP_AFTER) {
			$this->scheduler->reschedule(date('Y-m-d H:i:s', strtotime('+' . $duration . ' min ' . date('Y-m-d H:i:s'))), true);
		} else if ($plan->stop() == thermostatCyclePlan::STOP_CANCEL) {
			$this->scheduler->reschedule(null, true);
		}

		if ($this->memory->lastState() == 'heat' && $temporal_data['direction'] < 0) {
			$this->log->debug(__('Je dois refroidir mais avant je chauffais, je stop tout avant', __FILE__));
			$this->memory->setLastState('stop');
			$this->actuator->stop();
			sleep(5);
		}else if ($this->memory->lastState() == 'cool' && $temporal_data['direction'] > 0) {
			$this->log->debug(__('Je dois chauffer mais avant je refroidissait, je stop tout avant', __FILE__));
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
