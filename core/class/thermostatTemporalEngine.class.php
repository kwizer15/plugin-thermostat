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

	private $thermostat;
	private $actuator;
	private $scheduler;
	private $powerCalculator;
	private $smartStart;
	private $coefficientLearner;
	private $cyclePlanner;
	private $log;

	public function __construct($_thermostat, thermostatActuator $_actuator, thermostatScheduler $_scheduler, thermostatPowerCalculator $_powerCalculator, thermostatSmartStart $_smartStart, thermostatCoefficientLearner $_coefficientLearner, thermostatCyclePlanner $_cyclePlanner, thermostatLog $_log) {
		$this->thermostat = $_thermostat;
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
		$this->scheduler->reschedule(date('Y-m-d H:i:00', strtotime('+' . $this->thermostat->getConfiguration('cycle') . ' min ' . date('Y-m-d H:i:00'))));
		$this->log->debug(__('Reprogrammation automatique : ', __FILE__) . date('Y-m-d H:i:s', strtotime('+' . $this->thermostat->getConfiguration('cycle') . ' ' . __('minutes', __FILE__) . ' ' . date('Y-m-d H:i:00'))));
		$status = $this->thermostat->getCmd(null, 'status')->execCmd();
		if ($status == __('Suspendu', __FILE__)) {
			$this->log->debug(__('Thermostat suspendu', __FILE__));
			return;
		}
		if ($this->thermostat->getConfiguration('smart_start') == 1) {
			$this->log->debug(__('Programmation Smartstart', __FILE__));
			$this->smartStart->plan();
			$this->log->debug(__('Arrêt Smartstart', __FILE__));
		}
		$mode = $this->thermostat->getCmd(null, 'mode')->execCmd();
		if ($mode == 'Off') {
			$this->log->debug(__('Thermostat sur off', __FILE__));
			if ($status != __('Arrêté', __FILE__)) {
				$this->actuator->stop();
			}
			return;
		}
		$cmd = $this->thermostat->getCmd(null, 'temperature');
		$temp_in = $cmd->execCmd();
		if ($cmd->getCollectDate() != '' && $cmd->getCollectDate() < date('Y-m-d H:i:s', strtotime('-' . $this->thermostat->getConfiguration('maxTimeUpdateTemp') . ' minutes' . date('Y-m-d H:i:s')))) {
			if ($this->thermostat->getCache('temp_threshold', 0) == 0) {
				$this->actuator->failure();
				$this->log->error(__("Attention il n'y a pas eu de mise à jour de la température depuis plus de", __FILE__) . ' ' . $this->thermostat->getConfiguration('maxTimeUpdateTemp') . ' ' . __('minutes', __FILE__) . ' (' . $cmd->getCollectDate() . ')');
			}
			$this->log->debug(__("Je ne fais rien car il n'y a pas eu de mise a jour de la température depuis plus de", __FILE__) . ' ' . $this->thermostat->getConfiguration('maxTimeUpdateTemp') . ' ' . __('minutes', __FILE__));
			$this->thermostat->setCache('temp_threshold', 1);
			$this->thermostat->getCmd(null, 'status')->event(__('Défaillance sonde', __FILE__));
			return;
		}
		$temp_out = $this->thermostat->getCmd(null, 'temperature_outdoor')->execCmd();
		if (!is_numeric($temp_in)) {
			if ($this->thermostat->getCache('temp_threshold', 0) == 0) {
				$this->log->error(__("La température intérieure n'est pas un numérique", __FILE__) . ' : ' . $temp_in);
			}
			$this->log->debug(__("Je ne fais rien car la température intérieure n'est pas un numérique", __FILE__));
			$this->thermostat->setCache('temp_threshold', 1);
			$this->thermostat->getCmd(null, 'status')->event(__('Défaillance sonde', __FILE__));
			return;
		}
		$this->thermostat->setCache('temp_threshold', 0);
		$this->smartStart->learn($temp_in);
		if (($temp_in < ($this->thermostat->getCache('lastOrder', 0) - $this->thermostat->getConfiguration('offsetHeatFaillure', 1)) && $temp_in < $this->thermostat->getCache('lastTempIn', 0) && $this->thermostat->getCache('lastState') == 'heat' && $this->thermostat->getConfiguration('coeff_indoor_heat_autolearn') > 25) ||
			($temp_in > ($this->thermostat->getCache('lastOrder', 0) + $this->thermostat->getConfiguration('offsetColdFaillure', 1)) && $temp_in > $this->thermostat->getCache('lastTempIn', 0) && $this->thermostat->getCache('lastState') == 'cool' && $this->thermostat->getConfiguration('coeff_indoor_cool_autolearn') > 25)
		) {
			$this->thermostat->setCache('nbConsecutiveFaillure', $this->thermostat->getCache('nbConsecutiveFaillure', 0) + 1);
			if ($this->thermostat->getCache('nbConsecutiveFaillure', 0) == 2) {
				$this->log->error(__('Attention une défaillance du chauffage est détectée', __FILE__));
				$this->actuator->failureActuator();
			}
		} else {
			$this->thermostat->setCache('nbConsecutiveFaillure', 0);
		}
		$this->coefficientLearner->learn($temp_in, $temp_out);
		$delta = $this->thermostat->getCache('deltaOrder', 0);
		if ($delta > 0) {
			$this->log->debug(__('Delta consigne > 0', __FILE__) . ' (' . $delta . '), ' . __('je lance le calcul avec consigne - delta/2', __FILE__));
			$delta = $delta / 2;
		}
		$consigne = $this->thermostat->getCmd(null, 'order')->execCmd();
		$temporal_data = $this->powerCalculator->compute(floatval($consigne) - $delta, $this->thermostat->getCmd(null, 'temperature')->execCmd(), $this->thermostat->getCmd(null, 'temperature_outdoor')->execCmd());
		if ($temporal_data['power'] > 0 && $delta > 0) {
			$this->log->debug(__('Power > 0 et delta consigne > 0', __FILE__) . ' (' . $delta . '), ' . __('je relance le calcul avec consigne + delta/2', __FILE__));
			$temporal_data = $this->powerCalculator->compute($consigne + $delta, $this->thermostat->getCmd(null, 'temperature')->execCmd(), $this->thermostat->getCmd(null, 'temperature_outdoor')->execCmd());
		}
		$this->thermostat->setCache('last_power', $temporal_data['power']);
		$cycle = jeedom::evaluateExpression($this->thermostat->getConfiguration('cycle'));
		$plan = $this->cyclePlanner->plan($temporal_data['power'], $cycle, $this->thermostat->getCache('lastState') == 'heat', $this->thermostat->getConfiguration('minCycleDuration', 5), $this->thermostat->getConfiguration('stove_boiler'));
		$duration = $plan->duration();
		$this->thermostat->setCache('lastOrder', $consigne);
		$this->thermostat->setCache('lastTempIn', $temp_in);
		$this->thermostat->setCache('lastTempOut', $temp_out);
		$this->thermostat->setConfiguration('endDate', date('Y-m-d H:i:s', strtotime('+' . ceil($cycle * 0.9) . ' min ' . date('Y-m-d H:i:s'))));
		$this->log->debug(__('Durée du cycle', __FILE__) . '  : ' . $duration);
		if ($plan->isTooShort()) {
			$this->log->debug(__('Durée du cycle trop courte, aucun lancement', __FILE__));
			$this->thermostat->setCache('lastState', 'stop');
			$this->actuator->stop();
			$this->thermostat->save(true);
			return;
		}

		if ($plan->stop() == thermostatCyclePlan::STOP_AFTER) {
			$this->scheduler->reschedule(date('Y-m-d H:i:s', strtotime('+' . $duration . ' min ' . date('Y-m-d H:i:s'))), true);
		} else if ($plan->stop() == thermostatCyclePlan::STOP_CANCEL) {
			$this->scheduler->reschedule(null, true);
		}

		if ($this->thermostat->getCache('lastState','none') == 'heat' && $temporal_data['direction'] < 0) {
			$this->log->debug(__('Je dois refroidir mais avant je chauffais, je stop tout avant', __FILE__));
			$this->thermostat->setCache('lastState', 'stop');
			$this->actuator->stop();
			sleep(5);
		}else if ($this->thermostat->getCache('lastState','none') == 'cool' && $temporal_data['direction'] > 0) {
			$this->log->debug(__('Je dois chauffer mais avant je refroidissait, je stop tout avant', __FILE__));
			$this->thermostat->setCache('lastState', 'stop');
			$this->actuator->stop();
			sleep(5);
		}
		$this->thermostat->save(true);
		if ($duration > 0) {
			if ($temporal_data['direction'] > 0) {
				if ($this->actuator->heat()) {
					$this->thermostat->getCmd(null, 'power')->event(round($temporal_data['power']));
				}
			} else {
				if ($this->actuator->cool()) {
					$this->thermostat->getCmd(null, 'power')->event(round($temporal_data['power']));
				}
			}
		}
	}
}
