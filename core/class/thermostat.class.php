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

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/thermostatActionList.class.php';
require_once dirname(__FILE__) . '/thermostatPowerCalculator.class.php';
require_once dirname(__FILE__) . '/thermostatCoefficientLearner.class.php';
require_once dirname(__FILE__) . '/thermostatSmartStart.class.php';
require_once dirname(__FILE__) . '/thermostatActuator.class.php';
require_once dirname(__FILE__) . '/thermostatWindows.class.php';
require_once dirname(__FILE__) . '/thermostatScheduler.class.php';
require_once dirname(__FILE__) . '/thermostatHysteresisEngine.class.php';
require_once dirname(__FILE__) . '/thermostatTemporalEngine.class.php';
require_once dirname(__FILE__) . '/thermostatCommands.class.php';
require_once dirname(__FILE__) . '/thermostatConfiguration.class.php';

class thermostat extends eqLogic {

	public static function pull($_options = null) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			$cron = cron::byClassAndFunction(__CLASS__, 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			throw new Exception(__('Thermostat ID non trouvé', __FILE__) . ' : ' . $_options['thermostat_id'] . '. ' . __('Tâche supprimée', __FILE__));
		}
		if ($thermostat->getConfiguration('engine', 'temporal') != 'temporal') {
			$cron = cron::byClassAndFunction(__CLASS__, 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			return;
		}
		if (isset($_options['stop']) && $_options['stop'] == 1) {
			$status = $thermostat->getCmd(null, 'status')->execCmd();
			if ($status == __('Suspendu', __FILE__)) {
				return;
			}
			$thermostat->stopThermostat();
			return;
		} elseif (isset($_options['smartThermostat']) && $_options['smartThermostat'] == 1) {
			log::add(__CLASS__, 'debug', $thermostat->getHumanName() . ' ' . __('Thermostat::pull => mode smart', __FILE__) . ' : ' . print_r($_options, true));
			$cron = cron::byClassAndFunction(__CLASS__, 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove(false);
			}
			if (isset($_options['next']) && isset($_options['next']['calendar_id'])) {
				$calendar = calendar::byId($_options['next']['calendar_id']);
				if (is_object($calendar)) {
					$stateCalendar = $calendar->getCmd(null, 'state');
					if ($calendar->getIsEnable() == 0 || (is_object($stateCalendar) && $stateCalendar->execCmd() != 1)) {
						return;
					}
				}
			}
			if ($thermostat->getConfiguration('smart_start') == 1) {
				log::add(__CLASS__, 'debug', $thermostat->getHumanName() . ' ' . __('Next info', __FILE__) . ' : ' . print_r($_options['next'], true));
				$lockState = $thermostat->getCmd(null, 'lock_state');
				if (is_object($lockState) && $lockState->execCmd() == 1) {
					log::add(__CLASS__, 'debug', $thermostat->getHumanName() . ' ' . __('Thermostat verrouillé je ne fais rien', __FILE__));
				} else if ($_options['next']['type'] == 'thermostat') {
					log::add(__CLASS__, 'debug', $thermostat->getHumanName() . ' ' . __('Type thermostat envoi de la consigne', __FILE__) . ' : ' . $_options['next']['consigne']);
					(new thermostatSmartStart($thermostat))->remember($_options['next']);
					$cmd = $thermostat->getCmd(null, 'thermostat');
					$cmd->execCmd(array('slider' => $_options['next']['consigne']));
				} else if ($_options['next']['type'] == 'mode' && isset($_options['next']['cmd'])) {
					$mode = cmd::byId($_options['next']['cmd']);
					if (is_object($mode)) {
						log::add(__CLASS__, 'debug', $thermostat->getHumanName() . ' ' . __('Type mode envoi de la commande', __FILE__) . ' : ' . $_options['next']['cmd']);
						(new thermostatSmartStart($thermostat))->remember($_options['next']);
						$mode->execCmd();
					}
				}
			}
		} else {
			self::temporal($_options);
		}
	}

	public static function updatePerformance($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			return;
		}
		$dju = $thermostat->calculDju(date('Y-m-d'));
		if ($dju === null) {
			return;
		}
		$cmd = $thermostat->getCmd('info', 'performance');
		if (!is_object($cmd)) {
			return;
		}
		$performance = round(jeedom::evaluateExpression($thermostat->getConfiguration('consumption')) / $dju, 2);
		if ($performance <= 0) {
			return;
		}
		$cmd->event($performance);
	}

	public static function hysteresis($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat) || $thermostat->getIsEnable() == 0) {
			return;
		}
		(new thermostatHysteresisEngine($thermostat))->run();
	}

	public static function temporal($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat) || $thermostat->getIsEnable() == 0) {
			return;
		}
		(new thermostatTemporalEngine($thermostat))->run();
	}

	public static function cron() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			if ($thermostat->getConfiguration('repeat_commande_cron') != '') {
				try {
					$c = new Cron\CronExpression(checkAndFixCron($thermostat->getConfiguration('repeat_commande_cron')), new Cron\FieldFactory);
					if ($c->isDue()) {
						(new thermostatActuator($thermostat))->repeat();
					}
				} catch (Exception $e) {
					log::add(__CLASS__, 'error', $thermostat->getHumanName() . ' : ' . $e->getMessage());
				}
			}
			(new thermostatWindows($thermostat))->alert();
			(new thermostatScheduler($thermostat))->watchdog();
			(new thermostatHysteresisEngine($thermostat))->cron();

			if (strtolower($thermostat->getCmd(null, 'mode')->execCmd()) == 'off') {
				continue;
			}
			$temperature = $thermostat->getCmd(null, 'temperature');
			$temp_in = $temperature->execCmd();
			$failure = false;
			if ($thermostat->getConfiguration('maxTimeUpdateTemp') != '') {
				if ($temperature->getCollectDate() != '' && strtotime($temperature->getCollectDate()) < strtotime('-' . $thermostat->getConfiguration('maxTimeUpdateTemp') . ' minutes' . date('Y-m-d H:i:s'))) {
					if ($thermostat->getCache('temp_threshold', 0) == 0) {
						$thermostat->failure();
						log::add(__CLASS__, 'error', $thermostat->getHumanName() . ' ' . __("Attention il n'y a pas eu de mise à jour de la température depuis plus de", __FILE__) . ' : ' . $thermostat->getConfiguration('maxTimeUpdateTemp') . ' ' . __('minutes', __FILE__) . ' (' . $temperature->getCollectDate() . ')');
					}
					$failure = true;
				}
			}
			if ($thermostat->getConfiguration('temperature_indoor_min') != '' && is_numeric($thermostat->getConfiguration('temperature_indoor_min')) && $thermostat->getConfiguration('temperature_indoor_min') > $temp_in && $temp_in !== '') {
				if ($thermostat->getCache('temp_threshold', 0) == 0) {
					$thermostat->failure();
					log::add(__CLASS__, 'error', $thermostat->getHumanName() . ' ' . __('Attention la température intérieure est en dessous du seuil autorisé', __FILE__) . ' : ' . $temp_in);
				}
				$failure = true;
			}
			if ($thermostat->getConfiguration('temperature_indoor_max') != '' && is_numeric($thermostat->getConfiguration('temperature_indoor_max')) && $thermostat->getConfiguration('temperature_indoor_max') < $temp_in && $temp_in !== '') {
				if ($thermostat->getCache('temp_threshold', 0) == 0) {
					$thermostat->failure();
					log::add(__CLASS__, 'error', $thermostat->getHumanName() . ' ' . __('Attention la température intérieure est au dessus du seuil autorisé', __FILE__) . ' : ' . $temp_in);
				}
				$failure = true;
			}
			if (!$failure) {
				$thermostat->setCache('temp_threshold', 0);
			} else {
				$thermostat->setCache('temp_threshold', 1);
			}
		}
	}

	public static function start() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			if (strtolower($thermostat->getCmd(null, 'mode')->execCmd()) == 'off') {
				continue;
			}
			$thermostat->stopThermostat();
			$thermostat->runEngine();
		}
	}

	public static function window($_option) {
		$thermostat = thermostat::byId($_option['thermostat_id']);
		if (is_object($thermostat) && $thermostat->getIsEnable() == 1) {
			(new thermostatWindows($thermostat))->handle($_option);
		}
	}

	public static function deadCmd() {
		$return = array();
		foreach (eqLogic::byType('thermostat') as $thermostat) {
			$thermostat_json = json_encode(utils::o2a($thermostat));
			preg_match_all("/#([0-9]*)#/", $thermostat_json, $matches);
			foreach ($matches[1] as $cmd_id) {
				if (is_numeric($cmd_id)) {
					if (!cmd::byId($cmd_id)) {
						$return[] = array('detail' => 'Thermostat ' . $thermostat->getHumanName(), 'help' => 'Action', 'who' => '#' . $cmd_id . '#');
					}
				}
			}
		}
		return $return;
	}

	public function windowClose($_window) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::windowClose appelé de l\'extérieur, voir thermostatWindows');
		return (new thermostatWindows($this))->close($_window);
	}

	public function windowOpen($_window) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::windowOpen appelé de l\'extérieur, voir thermostatWindows');
		return (new thermostatWindows($this))->open($_window);
	}

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		(new thermostatScheduler($this))->reschedule($_next, $_stop, $_smartThermostat);
	}

	public function calculTemporalData($_consigne, $_allowOverfull = false) {
		return (new thermostatPowerCalculator($this))->compute($_consigne, $_allowOverfull);
	}

	public function getNextState() {
		return (new thermostatSmartStart($this))->plan();
	}

	public function preRemove() {
		(new thermostatScheduler($this))->unschedule();
	}

	public function preSave() {
		(new thermostatConfiguration($this))->apply();
	}

	public function postSave() {
		$commands = new thermostatCommands($this);
		$commands->define();
		$scheduler = new thermostatScheduler($this);
		if ($this->getIsEnable() == 1) {
			$windows = $this->getConfiguration('window');
			if (is_array($windows) && count($windows) > 0) {
				$events = array();
				foreach ($windows as $window) {
					$events[] = $window['cmd'];
				}
				$scheduler->listen('window', $events);
			}

			if ($this->getConfiguration('engine', 'temporal') == 'hysteresis') {
				preg_match_all("/#([0-9]*)#/", $this->getConfiguration('temperature_indoor'), $matches);
				$scheduler->listen('hysteresis', $matches[1]);
				$commands->removePower();
			} else {
				$scheduler->forget('hysteresis');
				$commands->definePower();
			}
			if ($this->getConfiguration('engine', 'temporal') != 'temporal' || $this->getIsEnable() != 1) {
				$cron = cron::byClassAndFunction(__CLASS__, 'pull', array('thermostat_id' => intval($this->getId())));
				if (is_object($cron)) {
					$this->stopThermostat();
					$cron->remove();
				}
			}
		} else {
			$scheduler->unschedule();
		}
	}

	public function rememberSmartStart($_next) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::rememberSmartStart appelé de l\'extérieur, voir thermostatSmartStart');
		return (new thermostatSmartStart($this))->remember($_next);
	}

	public function learnSmartStart($_temperature) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::learnSmartStart appelé de l\'extérieur, voir thermostatSmartStart');
		return (new thermostatSmartStart($this))->learn($_temperature);
	}

	public function learnCoefficient($_key, $_measured) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::learnCoefficient appelé de l\'extérieur, voir thermostatCoefficientLearner');
		return (new thermostatCoefficientLearner($this))->learnCoefficient($_key, $_measured);
	}

	public function runEngine() {
		if ($this->getConfiguration('engine', 'temporal') == 'temporal') {
			thermostat::temporal(array('thermostat_id' => $this->getId()));
		} else if ($this->getConfiguration('engine', 'temporal') == 'hysteresis') {
			thermostat::hysteresis(array('thermostat_id' => $this->getId()));
		}
	}

	public function heat($_repeat = false) {
		return (new thermostatActuator($this))->heat($_repeat);
	}

	public function cool($_repeat = false) {
		return (new thermostatActuator($this))->cool($_repeat);
	}

	public function stopThermostat($_repeat = false, $_suspend = false) {
		(new thermostatActuator($this))->stop($_repeat, $_suspend);
	}

	public function orderChange() {
		(new thermostatActuator($this))->orderChange();
	}

	public function failure($_failureRepeat = 999) {
		(new thermostatActuator($this))->failure();
	}

	public function failureActuator() {
		(new thermostatActuator($this))->failureActuator();
	}

	public function executeMode($_name) {
		(new thermostatActuator($this))->executeMode($_name);
	}

	public function runtimeByDay($_startDate = null, $_endDate = null) {
		$actifCmd = $this->getCmd(null, 'actif');
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

	public function calculDju($_date = null) {
		if ($_date == null) {
			$_date = date('Y-m-d');
		}
		$cmd = $this->getCmd(null, 'temperature_outdoor');
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

class thermostatCmd extends cmd {

	public function dontRemoveCmd() {
		return true;
	}

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		$lockState = $eqLogic->getCmd(null, 'lock_state');

		if ($this->getLogicalId() == 'deltaOrder') {
			$eqLogic->setCache('deltaOrder', $_options['slider']);
			return;
		} else if ($this->getLogicalId() == 'lock') {
			$lockState->event(1);
		} else if ($this->getLogicalId() == 'unlock') {
			$lockState->event(0);
		} else if ($this->getLogicalId() == 'offset_heat' || $this->getLogicalId() == 'offset_cool') {
			if (is_numeric($_options['slider'])) {
				$eqLogic->setConfiguration($this->getLogicalId(), $_options['slider']);
				$eqLogic->save();
			}
		} else if ($this->getLogicalId() == 'temperature') {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration('temperature_indoor',0)), 1);
		} else if ($this->getLogicalId() == 'temperature_outdoor') {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration('temperature_outdoor',0)), 1);
		} else if ($this->getLogicalId() == 'customCmd') {
			return jeedom::evaluateExpression($eqLogic->getConfiguration('customCmd'));
		} else if ($this->getLogicalId() == 'cool_only') {
			$eqLogic->setConfiguration('allow_mode', 'cool');
			$eqLogic->save();
			$eqLogic->runEngine();
		} else if ($this->getLogicalId() == 'heat_only') {
			$eqLogic->setConfiguration('allow_mode', 'heat');
			$eqLogic->save();
			$eqLogic->runEngine();
		} else if ($this->getLogicalId() == 'all_allow') {
			$eqLogic->setConfiguration('allow_mode', 'all');
			$eqLogic->save();
			$eqLogic->runEngine();
		}
		if (!is_object($lockState) || $lockState->execCmd() == 1) {
			$eqLogic->refreshWidget();
			return;
		}
		if ($this->getLogicalId() == 'modeAction') {
			$eqLogic->executeMode($this->getName());
		} else if ($this->getLogicalId() == 'off') {
			$eqLogic->stopThermostat(false);
			$eqLogic->getCmd(null, 'mode')->event(__('Off', __FILE__));
			$eqLogic->getCmd(null, 'status')->event(__('Arrêté', __FILE__));
		} else if ($this->getLogicalId() == 'thermostat') {
			if (!isset($_options['slider']) || !is_numeric(str_replace(',', '.', $_options['slider']))) {
				return;
			}
			$changed = ($eqLogic->getCmd(null, 'order')->execCmd() != $_options['slider']);
			$eqLogic->getCmd(null, 'order')->event($_options['slider']);
			if (!isset($_options['modeChange'])) {
				$eqLogic->getCmd(null, 'mode')->event(__('Aucun', __FILE__));
			}
			if ($eqLogic->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
				return;
			}
			$eqLogic->orderChange();
			if ($changed) {
				$eqLogic->runEngine();
			}
		}
	}
}
