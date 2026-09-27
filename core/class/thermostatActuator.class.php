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

class thermostatActuator {

	private $thermostat;

	public function __construct($_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function heat($_repeat = false) {
		if (!$_repeat) {
			if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
				return false;
			}
			if ($this->thermostat->getConfiguration('allow_mode', 'all') != 'all' && $this->thermostat->getConfiguration('allow_mode', 'all') != 'heat') {
				$this->stop();
				return false;
			}
			if (count($this->thermostat->getConfiguration('heating')) == 0) {
				$this->stop();
				return false;
			}
		}
		$this->thermostat->getCmd(null, 'status')->event(__('Chauffage', __FILE__));
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Action chauffage', __FILE__));
		(new thermostatActionList($this->thermostat))->execute($this->thermostat->getConfiguration('heating'), true);
		if (!$_repeat) {
			$this->thermostat->refresh();
			$this->thermostat->setCache('lastState', 'heat');
			$this->thermostat->getCmd(null, 'actif')->event(1);
		}
		return true;
	}

	public function cool($_repeat = false) {
		if (!$_repeat) {
			if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
				return false;
			}
			if ($this->thermostat->getConfiguration('allow_mode', 'all') != 'all' && $this->thermostat->getConfiguration('allow_mode', 'all') != 'cool') {
				$this->stop();
				return false;
			}
			if (count($this->thermostat->getConfiguration('cooling')) == 0) {
				$this->stop();
				return false;
			}
		}
		$this->thermostat->getCmd(null, 'status')->event(__('Climatisation', __FILE__));
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Action froid', __FILE__));
		(new thermostatActionList($this->thermostat))->execute($this->thermostat->getConfiguration('cooling'), true);
		if (!$_repeat) {
			$this->thermostat->refresh();
			$this->thermostat->setCache('lastState', 'cool');
			$this->thermostat->getCmd(null, 'actif')->event(1);
		}
		return true;
	}

	public function stop($_repeat = false, $_suspend = false) {
		if (!$_repeat && $this->thermostat->getCmd(null, 'status')->execCmd() == __('Arrêté', __FILE__)) {
			$power = $this->thermostat->getCmd(null, 'power');
			if (is_object($power) && $power->execCmd() > 0) {
				$_repeat = true;
			}else{
			   return;
			}
		}
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Action stop', __FILE__));
		(new thermostatActionList($this->thermostat))->execute($this->thermostat->getConfiguration('stoping'), true);
		$power = $this->thermostat->getCmd(null, 'power');
		if (is_object($power)) {
			$power->event(0);
		}
		$this->thermostat->getCmd(null, 'actif')->event(0);

		if (!$_suspend) {
			$this->thermostat->getCmd(null, 'status')->event(__('Arrêté', __FILE__));
		}
		if ($_repeat) {
			return;
		}
		$this->thermostat->save(true);
	}

	public function orderChange() {
		if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			return;
		}
		if (!is_array($this->thermostat->getConfiguration('orderChange')) || count($this->thermostat->getConfiguration('orderChange')) == 0) {
			return;
		}
		(new thermostatActionList($this->thermostat))->execute($this->thermostat->getConfiguration('orderChange'), true, array('modeChange' => true));
	}

	public function failure() {
		if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			return;
		}
		if (!is_array($this->thermostat->getConfiguration('failure')) || count($this->thermostat->getConfiguration('failure')) == 0) {
			return;
		}
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Action défaillance sonde', __FILE__));
		(new thermostatActionList($this->thermostat))->execute($this->thermostat->getConfiguration('failure'), false);
		$this->thermostat->getCmd(null, 'status')->event(__('Défaillance sonde', __FILE__));
	}

	public function failureActuator() {
		if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			return;
		}
		if (!is_array($this->thermostat->getConfiguration('failureActuator')) || count($this->thermostat->getConfiguration('failureActuator')) == 0) {
			return;
		}
		log::add('thermostat', 'debug', $this->thermostat->getHumanName() . ' ' . __('Action défaillance chauffage', __FILE__));
		(new thermostatActionList($this->thermostat))->execute($this->thermostat->getConfiguration('failureActuator'), false);
		$this->thermostat->getCmd(null, 'status')->event(__('Défaillance chauffage', __FILE__));
	}

	public function executeMode($_name) {
		$thermostatCmd = false;
		$consigne = $this->thermostat->getCmd(null, 'order')->execCmd();
		foreach ($this->thermostat->getConfiguration('existingMode') as $existingMode) {
			if ($_name == $existingMode['name']) {
				foreach ($existingMode['actions'] as $action) {
					try {
						$options = thermostatActionList::options($action, $consigne);
						$cmd = (is_numeric(str_replace('#', '', $action['cmd']))) ? cmd::byString($action['cmd']) : '';
						if (is_object($cmd) && $cmd->getEqLogic_id() == $this->thermostat->getId() && $cmd->getLogicalId() == 'thermostat') {
							$thermostatCmd = true;
							$this->thermostat->getCmd(null, 'order')->event(scenarioExpression::createAndExec('condition', $options['slider']));
						} else {
							scenarioExpression::createAndExec('action', $action['cmd'], $options);
						}
					} catch (Exception $e) {
						(new thermostatActionList($this->thermostat))->logError($action, $e);
					}
				}
			}
		}
		$this->thermostat->getCmd(null, 'mode')->event($_name);
		if ($thermostatCmd == true) {
			$this->orderChange();
		}
		$this->thermostat->runEngine();
	}

	public function repeat() {
		switch ($this->thermostat->getCmd(null, 'status')->execCmd()) {
			case __('Chauffage', __FILE__):
				$this->heat(true);
				break;
			case __('Arrêté', __FILE__):
				$this->stop(true);
				break;
			case __('Climatisation', __FILE__):
				$this->cool(true);
				break;
		}
	}
}
