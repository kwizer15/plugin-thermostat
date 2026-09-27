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

namespace Jeedom\Plugin\Thermostat\Jeedom;

use Jeedom\Plugin\Thermostat\Domain\Command\LogicalId;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Key;
use Jeedom\Plugin\Thermostat\Domain\Engine\EngineType;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Scheduling;

class Scheduler implements Scheduling {

	/** @var \thermostat */
	private $thermostat;
	/** @var Log */
	private $log;

	public function __construct(\thermostat $_thermostat, Log $_log) {
		$this->thermostat = $_thermostat;
		$this->log = $_log;
	}

	/**
	 * @return array{thermostat_id: int}
	 */
	private function options() {
		return array(Callback::OPTION_THERMOSTAT_ID => intval($this->thermostat->getId()));
	}

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$this->log->debug('Reschedule, next : '.$_next.', stop : '.$_stop.', smartThermostat : '.json_encode($_smartThermostat));
		$options = array(Callback::OPTION_THERMOSTAT_ID => intval($this->thermostat->getId()));
		if ($_stop) {
			$options[Callback::OPTION_STOP] = intval(1);
		}
		if ($_smartThermostat !== false) {
			$crons = \cron::searchClassAndFunction(\thermostat::class, Callback::PULL, '"thermostat_id":' . intval($this->thermostat->getId()) . '%"smartThermostat":1');
			if (is_array($crons) && count($crons)) {
				foreach ($crons as $cron) {
					$cron->remove(false);
				}
			}
			$options[Callback::OPTION_SMART_THERMOSTAT] = intval(1);
			$options['next'] = $_smartThermostat;
		}
		$cron = \cron::byClassAndFunction(\thermostat::class, Callback::PULL, $options);
		if (is_object($cron)) {
			$cron->remove(false);
		}
		if($_next == null){
			return;
		}
		$cron = new \cron();
		$cron->setClass(\thermostat::class);
		$cron->setFunction(Callback::PULL);
		$cron->setOption($options);
		$_next = strtotime($_next);
		$cron->setTimeout($this->thermostat->getConfiguration(Key::CYCLE) + 10);
		$cron->setSchedule(\cron::convertDateToCron($_next));
		$cron->setOnce(1);
		$cron->save();
	}

	/**
	 * @return void
	 */
	public function unschedule() {
		$cron = \cron::byClassAndFunction(\thermostat::class, Callback::PULL, $this->options());
		if (is_object($cron)) {
			$cron->remove();
		}
		$cron = \cron::byClassAndFunction(\thermostat::class, Callback::PULL, array(Callback::OPTION_THERMOSTAT_ID => intval($this->thermostat->getId()), Callback::OPTION_STOP => intval(1)));
		if (is_object($cron)) {
			$cron->remove();
		}
		$this->forget(Callback::WINDOW);
		$this->forget(Callback::HYSTERESIS);
		$this->forget(Callback::UPDATE_PERFORMANCE);
	}

	/**
	 * @param string $_function
	 * @param list<string> $_events
	 * @return void
	 */
	public function listen($_function, $_events) {
		$listener = \listener::byClassAndFunction(\thermostat::class, $_function, $this->options());
		if (!is_object($listener)) {
			$listener = new \listener();
		}
		$listener->setClass(\thermostat::class);
		$listener->setFunction($_function);
		$listener->setOption($this->options());
		$listener->emptyEvent();
		foreach ($_events as $event) {
			$listener->addEvent($event);
		}
		$listener->save();
	}

	/**
	 * @param string $_function
	 * @return void
	 */
	public function forget($_function) {
		$listener = \listener::byClassAndFunction(\thermostat::class, $_function, $this->options());
		if (is_object($listener)) {
			$listener->remove();
		}
	}

	/**
	 * @return void
	 */
	public function watchdog() {
		if ($this->thermostat->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) == EngineType::TEMPORAL && date('i') % 10 == 0) {
			$cron = \cron::byClassAndFunction(\thermostat::class, Callback::PULL, array(Callback::OPTION_THERMOSTAT_ID => intval($this->thermostat->getId())));
			if (!is_object($cron)) {
				$this->reschedule(date('Y-m-d H:i:s', strtotime('+2 min ' . date('Y-m-d H:i:s'))));
			} else {
				if ($cron->getState() != 'run') {
					try {
						$c = new \Cron\CronExpression(checkAndFixCron($cron->getSchedule()), new \Cron\FieldFactory);
						if (!$c->isDue()) {
							$c->getNextRunDate();
						}
					} catch (\Exception $ex) {
						$this->reschedule(date('Y-m-d H:i:s', strtotime('+2 min ' . date('Y-m-d H:i:s'))));
					}
				}
			}
		}
	}

	/**
	 * @return void
	 */
	public function runHysteresisCron() {
		if ($this->thermostat->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) == EngineType::HYSTERESIS && $this->thermostat->getConfiguration(Key::HYSTERESIS_CRON) != '') {
			try {
				$c = new \Cron\CronExpression(checkAndFixCron($this->thermostat->getConfiguration(Key::HYSTERESIS_CRON)), new \Cron\FieldFactory);
				if ($c->isDue()) {
					$this->thermostat->getCmd(null, LogicalId::TEMPERATURE)->event(\jeedom::evaluateExpression($this->thermostat->getConfiguration(Key::TEMPERATURE_INDOOR)));
					\thermostat::hysteresis(array(Callback::OPTION_THERMOSTAT_ID => $this->thermostat->getId()));
				}
			} catch (\Exception $e) {
				$this->log->error(': ' . $e->getMessage());
			}
		}
	}
}
