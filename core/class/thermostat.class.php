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
use Jeedom\Plugin\Thermostat\Domain\Command\LogicalId;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Key;
use Jeedom\Plugin\Thermostat\Domain\Engine\EngineType;
use Jeedom\Plugin\Thermostat\Jeedom\Callback;
use Jeedom\Plugin\Thermostat\Jeedom\MissingCommand;

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

	/** @var Assembly|null */
	private $_assembly;

	public function assembly(): Assembly {
		if ($this->_assembly === null) {
			$this->_assembly = new Assembly($this);
		}
		return $this->_assembly;
	}

	public static function pull($_options = null) {
		$thermostat = thermostat::byId($_options[Callback::OPTION_THERMOSTAT_ID]);
		if (!is_object($thermostat)) {
			$cron = cron::byClassAndFunction(__CLASS__, Callback::PULL, $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			throw new Exception(__('Thermostat ID non trouvé', __FILE__) . ' : ' . $_options[Callback::OPTION_THERMOSTAT_ID] . '. ' . __('Tâche supprimée', __FILE__));
		}
		if ($thermostat->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) != EngineType::TEMPORAL) {
			$cron = cron::byClassAndFunction(__CLASS__, Callback::PULL, $_options);
			if (is_object($cron)) {
				$cron->remove();
			}
			return;
		}
		try {
			if (isset($_options[Callback::OPTION_STOP]) && $_options[Callback::OPTION_STOP] == 1) {
				$status = $thermostat->assembly()->display()->status();
				if ($status == __('Suspendu', __FILE__)) {
					return;
				}
				$thermostat->stopThermostat();
				return;
			} elseif (isset($_options[Callback::OPTION_SMART_THERMOSTAT]) && $_options[Callback::OPTION_SMART_THERMOSTAT] == 1) {
				log::add(__CLASS__, 'debug', $thermostat->getHumanName() . ' ' . __('Thermostat::pull => mode smart', __FILE__) . ' : ' . print_r($_options, true));
				$cron = cron::byClassAndFunction(__CLASS__, Callback::PULL, $_options);
				if (is_object($cron)) {
					$cron->remove(false);
				}
				$thermostat->assembly()->smartStart()->trigger($_options);
			} else {
				self::temporal($_options);
			}
		} catch (MissingCommand $e) {
			$thermostat->assembly()->log()->error($e->getMessage());
		}
	}

	public static function updatePerformance($_options) {
		$thermostat = thermostat::byId($_options[Callback::OPTION_THERMOSTAT_ID]);
		if (!is_object($thermostat)) {
			return;
		}
		$thermostat->assembly()->statistics()->updatePerformance();
	}

	public static function hysteresis($_options) {
		$thermostat = thermostat::byId($_options[Callback::OPTION_THERMOSTAT_ID]);
		if (!is_object($thermostat) || $thermostat->getIsEnable() == 0) {
			return;
		}
		try {
			$thermostat->assembly()->hysteresisEngine()->run();
		} catch (MissingCommand $e) {
			$thermostat->assembly()->log()->error($e->getMessage());
		}
	}

	public static function temporal($_options) {
		$thermostat = thermostat::byId($_options[Callback::OPTION_THERMOSTAT_ID]);
		if (!is_object($thermostat) || $thermostat->getIsEnable() == 0) {
			return;
		}
		try {
			$thermostat->assembly()->temporalEngine()->run();
		} catch (MissingCommand $e) {
			$thermostat->assembly()->log()->error($e->getMessage());
		}
	}

	public static function cron() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			try {
				if ($thermostat->getConfiguration(Key::REPEAT_CRON) != '') {
					try {
						$c = new Cron\CronExpression(checkAndFixCron($thermostat->getConfiguration(Key::REPEAT_CRON)), new Cron\FieldFactory);
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
			} catch (MissingCommand $e) {
				$thermostat->assembly()->log()->error($e->getMessage());
			}
		}
	}

	public static function start() {
		foreach (thermostat::byType('thermostat', true) as $thermostat) {
			try {
				if ($thermostat->assembly()->display()->isOff()) {
					continue;
				}
				$thermostat->stopThermostat();
				$thermostat->runEngine();
			} catch (MissingCommand $e) {
				$thermostat->assembly()->log()->error($e->getMessage());
			}
		}
	}

	public static function window($_option) {
		$thermostat = thermostat::byId($_option[Callback::OPTION_THERMOSTAT_ID]);
		if (is_object($thermostat) && $thermostat->getIsEnable() == 1) {
			try {
				$thermostat->assembly()->windows()->handle($_option);
			} catch (MissingCommand $e) {
				$thermostat->assembly()->log()->error($e->getMessage());
			}
		}
	}

	public static function windowTimer($_options) {
		$thermostat = thermostat::byId($_options[Callback::OPTION_THERMOSTAT_ID]);
		if (is_object($thermostat) && $thermostat->getIsEnable() == 1) {
			try {
				$thermostat->assembly()->windows()->timer($_options[Callback::OPTION_CMD], $_options[Callback::OPTION_PHASE]);
			} catch (MissingCommand $e) {
				$thermostat->assembly()->log()->error($e->getMessage());
			}
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

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$this->assembly()->scheduler()->reschedule($_next, $_stop, $_smartThermostat);
	}

	public function calculTemporalData($_consigne, $_allowOverfull = false) {
		return $this->assembly()->powerCalculator()->compute($_consigne, $this->getCmd(null, LogicalId::TEMPERATURE)->execCmd(), $this->getCmd(null, LogicalId::TEMPERATURE_OUTDOOR)->execCmd(), $_allowOverfull);
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
			$windows = $this->getConfiguration(Key::WINDOWS);
			if (is_array($windows) && count($windows) > 0) {
				$events = array();
				foreach ($windows as $window) {
					$events[] = $window['cmd'];
				}
				$scheduler->listen(Callback::WINDOW, $events);
			}

			if ($this->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) == EngineType::HYSTERESIS) {
				preg_match_all("/#([0-9]*)#/", $this->getConfiguration(Key::TEMPERATURE_INDOOR), $matches);
				$scheduler->listen(Callback::HYSTERESIS, $matches[1]);
				$commands->removePower();
			} else {
				$scheduler->forget(Callback::HYSTERESIS);
				$commands->definePower();
			}
			if ($this->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) != EngineType::TEMPORAL) {
				$cron = cron::byClassAndFunction(__CLASS__, Callback::PULL, array(Callback::OPTION_THERMOSTAT_ID => intval($this->getId())));
				if (is_object($cron)) {
					$this->stopThermostat();
					$cron->remove();
				}
			}
		} else {
			$scheduler->unschedule();
		}
	}

	public function runEngine() {
		if ($this->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) == EngineType::TEMPORAL) {
			thermostat::temporal(array(Callback::OPTION_THERMOSTAT_ID => $this->getId()));
		} else if ($this->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) == EngineType::HYSTERESIS) {
			thermostat::hysteresis(array(Callback::OPTION_THERMOSTAT_ID => $this->getId()));
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

	public function failure() {
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
		if ($this->getLogicalId() == LogicalId::TEMPERATURE) {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration(Key::TEMPERATURE_INDOOR,0)), 1);
		} else if ($this->getLogicalId() == LogicalId::TEMPERATURE_OUTDOOR) {
			return round(jeedom::evaluateExpression($eqLogic->getConfiguration(Key::TEMPERATURE_OUTDOOR,0)), 1);
		} else if ($this->getLogicalId() == LogicalId::CUSTOM_CMD) {
			return jeedom::evaluateExpression($eqLogic->getConfiguration(Key::CUSTOM_CMD));
		}
		return $eqLogic->assembly()->commandHandler()->handle($this->getLogicalId(), $this->getName(), $_options);
	}
}
