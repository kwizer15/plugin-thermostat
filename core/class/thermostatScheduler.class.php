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

class thermostatScheduler implements thermostatScheduling {

	private $thermostat;
	private $log;

	public function __construct($_thermostat, thermostatLog $_log) {
		$this->thermostat = $_thermostat;
		$this->log = $_log;
	}

	private function options() {
		return array('thermostat_id' => intval($this->thermostat->getId()));
	}

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$this->log->debug('Reschedule, next : '.$_next.', stop : '.$_stop.', smartThermostat : '.json_encode($_smartThermostat));
		$options = array('thermostat_id' => intval($this->thermostat->getId()));
		if ($_stop) {
			$options['stop'] = intval(1);
		}
		if ($_smartThermostat !== false) {
			$crons = cron::searchClassAndFunction('thermostat', 'pull', '"thermostat_id":' . intval($this->thermostat->getId()) . '%"smartThermostat":1');
			if (is_array($crons) && count($crons)) {
				foreach ($crons as $cron) {
					$cron->remove(false);
				}
			}
			$options['smartThermostat'] = intval(1);
			$options['next'] = $_smartThermostat;
		}
		$cron = cron::byClassAndFunction('thermostat', 'pull', $options);
		if (is_object($cron)) {
			$cron->remove(false);
		}
		if($_next == null){
			return;
		}
		$cron = new cron();
		$cron->setClass('thermostat');
		$cron->setFunction('pull');
		$cron->setOption($options);
		$_next = strtotime($_next);
		$cron->setTimeout($this->thermostat->getConfiguration('cycle') + 10);
		$cron->setSchedule(cron::convertDateToCron($_next));
		$cron->setOnce(1);
		$cron->save();
	}

	public function unschedule() {
		$cron = cron::byClassAndFunction('thermostat', 'pull', $this->options());
		if (is_object($cron)) {
			$cron->remove();
		}
		$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->thermostat->getId()), 'stop' => intval(1)));
		if (is_object($cron)) {
			$cron->remove();
		}
		$this->forget('window');
		$this->forget('hysteresis');
		$this->forget('updatePerformance');
	}

	public function listen($_function, $_events) {
		$listener = listener::byClassAndFunction('thermostat', $_function, $this->options());
		if (!is_object($listener)) {
			$listener = new listener();
		}
		$listener->setClass('thermostat');
		$listener->setFunction($_function);
		$listener->setOption($this->options());
		$listener->emptyEvent();
		foreach ($_events as $event) {
			$listener->addEvent($event);
		}
		$listener->save();
	}

	public function forget($_function) {
		$listener = listener::byClassAndFunction('thermostat', $_function, $this->options());
		if (is_object($listener)) {
			$listener->remove();
		}
	}

	public function watchdog() {
		if ($this->thermostat->getConfiguration('engine', 'temporal') == 'temporal' && date('i') % 10 == 0) {
			$cron = cron::byClassAndFunction('thermostat', 'pull', array('thermostat_id' => intval($this->thermostat->getId())));
			if (!is_object($cron)) {
				$this->reschedule(date('Y-m-d H:i:s', strtotime('+2 min ' . date('Y-m-d H:i:s'))));
			} else {
				if ($cron->getState() != 'run') {
					try {
						$c = new Cron\CronExpression(checkAndFixCron($cron->getSchedule()), new Cron\FieldFactory);
						if (!$c->isDue()) {
							$c->getNextRunDate();
						}
					} catch (Exception $ex) {
						$this->reschedule(date('Y-m-d H:i:s', strtotime('+2 min ' . date('Y-m-d H:i:s'))));
					}
				}
			}
		}
	}

	public function runHysteresisCron() {
		if ($this->thermostat->getConfiguration('engine', 'temporal') == 'hysteresis' && $this->thermostat->getConfiguration('hysteresis_cron') != '') {
			try {
				$c = new Cron\CronExpression(checkAndFixCron($this->thermostat->getConfiguration('hysteresis_cron')), new Cron\FieldFactory);
				if ($c->isDue()) {
					$this->thermostat->getCmd(null, 'temperature')->event(jeedom::evaluateExpression($this->thermostat->getConfiguration('temperature_indoor')));
					thermostat::hysteresis(array('thermostat_id' => $this->thermostat->getId()));
				}
			} catch (Exception $e) {
				$this->log->error(': ' . $e->getMessage());
			}
		}
	}
}
