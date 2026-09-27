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
use Jeedom\Plugin\Thermostat\Assembly;
use Jeedom\Plugin\Thermostat\Domain\Engine\EngineType;

spl_autoload_register(function ($_class) {
	$prefix = 'Jeedom\\Plugin\\Thermostat\\';
	if (strpos($_class, $prefix) !== 0) {
		return;
	}
	$file = dirname(__FILE__) . '/../src/' . str_replace('\\', '/', substr($_class, strlen($prefix))) . '.php';
	if (file_exists($file)) {
		require_once $file;
	}
});

class thermostat extends eqLogic {

	public function assembly() {
		return new Assembly($this);
	}

	public static function pull($_options = null) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			$cron = cron::byClassAndFunction(__CLASS__, 'pull', $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			throw new Exception(__('Thermostat ID non trouvé', __FILE__) . ' : ' . $_options['thermostat_id'] . '. ' . __('Tâche supprimée', __FILE__));
		}
		if ($thermostat->getConfiguration('engine', EngineType::TEMPORAL) != EngineType::TEMPORAL) {
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
			$thermostat->assembly()->smartStart()->trigger($_options);
		} else {
			self::temporal($_options);
		}
	}

	public static function updatePerformance($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat)) {
			return;
		}
		$thermostat->assembly()->statistics()->updatePerformance();
	}

	public static function hysteresis($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat) || $thermostat->getIsEnable() == 0) {
			return;
		}
		$thermostat->assembly()->hysteresisEngine()->run();
	}

	public static function temporal($_options) {
		$thermostat = thermostat::byId($_options['thermostat_id']);
		if (!is_object($thermostat) || $thermostat->getIsEnable() == 0) {
			return;
		}
		$thermostat->assembly()->temporalEngine()->run();
	}

	public static function cron() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			if ($thermostat->getConfiguration('repeat_commande_cron') != '') {
				try {
					$c = new Cron\CronExpression(checkAndFixCron($thermostat->getConfiguration('repeat_commande_cron')), new Cron\FieldFactory);
					if ($c->isDue()) {
						$thermostat->assembly()->actuator()->repeat();
					}
				} catch (Exception $e) {
					log::add(__CLASS__, 'error', $thermostat->getHumanName() . ' : ' . $e->getMessage());
				}
			}
			$thermostat->assembly()->windows()->alert();
			$thermostat->assembly()->scheduler()->watchdog();
			$thermostat->assembly()->scheduler()->runHysteresisCron();
			$thermostat->assembly()->sensorWatch()->check();
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
			$thermostat->assembly()->windows()->handle($_option);
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
		return $this->assembly()->windows()->close($_window);
	}

	public function windowOpen($_window) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::windowOpen appelé de l\'extérieur, voir thermostatWindows');
		return $this->assembly()->windows()->open($_window);
	}

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$this->assembly()->scheduler()->reschedule($_next, $_stop, $_smartThermostat);
	}

	public function calculTemporalData($_consigne, $_allowOverfull = false) {
		return $this->assembly()->powerCalculator()->compute($_consigne, $this->getCmd(null, 'temperature')->execCmd(), $this->getCmd(null, 'temperature_outdoor')->execCmd(), $_allowOverfull);
	}

	public function getNextState() {
		return $this->assembly()->smartStart()->plan();
	}

	public function preRemove() {
		$this->assembly()->scheduler()->unschedule();
	}

	public function preSave() {
		$this->assembly()->configuration()->apply();
	}

	public function postSave() {
		$commands = $this->assembly()->commands();
		$commands->define();
		$scheduler = $this->assembly()->scheduler();
		if ($this->getIsEnable() == 1) {
			$windows = $this->getConfiguration('window');
			if (is_array($windows) && count($windows) > 0) {
				$events = array();
				foreach ($windows as $window) {
					$events[] = $window['cmd'];
				}
				$scheduler->listen('window', $events);
			}

			if ($this->getConfiguration('engine', EngineType::TEMPORAL) == EngineType::HYSTERESIS) {
				preg_match_all("/#([0-9]*)#/", $this->getConfiguration('temperature_indoor'), $matches);
				$scheduler->listen('hysteresis', $matches[1]);
				$commands->removePower();
			} else {
				$scheduler->forget('hysteresis');
				$commands->definePower();
			}
			if ($this->getConfiguration('engine', EngineType::TEMPORAL) != EngineType::TEMPORAL || $this->getIsEnable() != 1) {
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
		return $this->assembly()->smartStart()->remember($_next);
	}

	public function learnSmartStart($_temperature) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::learnSmartStart appelé de l\'extérieur, voir thermostatSmartStart');
		return $this->assembly()->smartStart()->learn($_temperature);
	}

	public function learnCoefficient($_key, $_measured) {
		log::add(__CLASS__, 'warning', $this->getHumanName() . ' thermostat::learnCoefficient appelé de l\'extérieur, voir thermostatCoefficientLearner');
		return $this->assembly()->coefficientLearner()->learnCoefficient($_key, $_measured);
	}

	public function runEngine() {
		if ($this->getConfiguration('engine', EngineType::TEMPORAL) == EngineType::TEMPORAL) {
			thermostat::temporal(array('thermostat_id' => $this->getId()));
		} else if ($this->getConfiguration('engine', EngineType::TEMPORAL) == EngineType::HYSTERESIS) {
			thermostat::hysteresis(array('thermostat_id' => $this->getId()));
		}
	}

	public function heat($_repeat = false) {
		return $this->assembly()->actuator()->heat($_repeat);
	}

	public function cool($_repeat = false) {
		return $this->assembly()->actuator()->cool($_repeat);
	}

	public function stopThermostat($_repeat = false, $_suspend = false) {
		$this->assembly()->actuator()->stop($_repeat, $_suspend);
	}

	public function orderChange() {
		$this->assembly()->actuator()->orderChange();
	}

	public function failure($_failureRepeat = 999) {
		$this->assembly()->actuator()->failure();
	}

	public function failureActuator() {
		$this->assembly()->actuator()->failureActuator();
	}

	public function executeMode($_name) {
		$this->assembly()->actuator()->executeMode($_name);
	}

	public function runtimeByDay($_startDate = null, $_endDate = null) {
		return $this->assembly()->statistics()->runtimeByDay($_startDate, $_endDate);
	}

	public function calculDju($_date = null) {
		return $this->assembly()->statistics()->dju($_date);
	}
}

class thermostatCmd extends cmd {

	public function dontRemoveCmd() {
		return true;
	}

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		if ($this->getLogicalId() == 'temperature') {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration('temperature_indoor',0)), 1);
		} else if ($this->getLogicalId() == 'temperature_outdoor') {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration('temperature_outdoor',0)), 1);
		} else if ($this->getLogicalId() == 'customCmd') {
			return jeedom::evaluateExpression($eqLogic->getConfiguration('customCmd'));
		}
		return $eqLogic->assembly()->commandHandler()->handle($this->getLogicalId(), $this->getName(), $_options);
	}
}
