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

class thermostatWindows {

	private $thermostat;
	private $actuator;
	private $log;

	public function __construct($_thermostat, thermostatActuator $_actuator, thermostatLog $_log) {
		$this->thermostat = $_thermostat;
		$this->actuator = $_actuator;
		$this->log = $_log;
	}

	public function handle($_option) {
		$this->log->debug(__("Détection d'un changement sur une fenêtre", __FILE__));
		$windows = $this->thermostat->getConfiguration('window');
		foreach ($windows as $window) {
			if ('#' . $_option['event_id'] . '#' == $window['cmd']) {
				if (isset($window['invert']) && $window['invert'] == 1) {
					$_option['value'] = ($_option['value'] == 0) ? 1 : 0;
				}
				$this->log->debug(__('Fenêtre trouvée', __FILE__) . ' : ' . cmd::byString($window['cmd'])->getHumanName() . ' - ' . __('valeur', __FILE__) . ' : ' . $_option['value']);
				if ($_option['value'] == 0) {
					$this->log->debug(__('Fenêtre fermée', __FILE__));
					$this->close($window);
				} else {
					$this->log->debug(__('Fenêtre ouverte', __FILE__));
					$this->open($window);
				}
			}
		}
	}

	public function open($_window) {
		$this->log->debug('[windowOpen] => ' . json_encode($_window));
		$this->thermostat->setCache('window::state::' . str_replace('#', '', $_window['cmd']), 1);
		if ($this->thermostat->getCmd(null, 'mode')->execCmd() == __('Off', __FILE__) || $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)) {
			$this->log->debug('[windowOpen] ' . __('Thermostat arreté ou suspendu je ne fais rien', __FILE__));
			return;
		}
		$startime = strtotime('now');
		$cmd = cmd::byId(str_replace('#', '', $_window['cmd']));
		if (!is_object($cmd)) {
			$this->log->debug('[windowOpen] ' . __('Commande introuvable je ne fais rien', __FILE__));
			return;
		}
		$stopTime = (isset($_window['stopTime']) && $_window['stopTime'] != '') ? $_window['stopTime'] : 0;
		if (is_numeric($stopTime) && $stopTime > 0) {
			$this->log->debug('[windowOpen] ' . __('Pause de', __FILE__) . ' ' . $stopTime . ' ' . __('minutes', __FILE__));
			sleep($stopTime * 60);
		}
		$value = $cmd->execCmd();
		if (isset($_window['invert']) && $_window['invert'] == 1) {
			$value = ($value == 0) ? 1 : 0;
		}
		$this->log->debug('[windowOpen] ' . __('Valeur commande', __FILE__) . ' : ' . $value . __(' en date du : ', __FILE__) . $cmd->getValueDate());
		if ($value != 1) {
			$this->log->debug('[windowOpen] ' . __("L'ouvrant n'est plus ouvert, je ne fais rien", __FILE__));
			return true;
		}
		if (strtotime($cmd->getValueDate()) > ($startime + 5)) {
			$this->log->debug('[windowOpen] ' . __("L'ouvrant à été refermé pendant la pause, je ne fais rien, refermé à", __FILE__) . ' ' . $cmd->getValueDate());
			return true;
		}
		$this->log->debug('[windowOpen] ' . __('Arrêt du thermostat', __FILE__));
		$this->thermostat->getCmd(null, 'status')->event(__('Suspendu', __FILE__));
		$this->actuator->stop(false, true);
		$this->thermostat->setCache('window::state::open', strtotime('now'));
		return true;
	}

	public function close($_window) {
		if ($this->thermostat->getCache('window::state::' . str_replace('#', '', $_window['cmd']), 0) != 1) {
			$this->log->debug('[windowClose] ' . __("Je n'ai jamais vu cette fenêtre ouverte, je ne fais rien", __FILE__));
			return;
		}
		$this->thermostat->setCache('window::state::' . str_replace('#', '', $_window['cmd']), 0);
		$this->log->debug('[windowClose] => ' . json_encode($_window));
		if ($this->thermostat->getCmd(null, 'status')->execCmd() != __('Suspendu', __FILE__)) {
			$this->log->debug('[windowClose] ' . __('Thermostat non suspendu je ne fais rien', __FILE__));
			return;
		}
		$this->thermostat->setCache('window::close::' . str_replace('#', '', $_window['cmd']) . '::datetime', date('Y-m-d H:i:s'));
		$restartTime = (isset($_window['restartTime']) && $_window['restartTime'] != '') ? $_window['restartTime'] * 60 : 0;
		if (is_numeric($restartTime) && $restartTime > 0) {
			$this->log->debug('[windowClose] ' . __('Pause de', __FILE__) . ' ' . $restartTime . 's');
			sleep($restartTime);
		}
		$windows = $this->thermostat->getConfiguration('window');
		foreach ($windows as $window) {
			$cmd = cmd::byId(str_replace('#', '', $window['cmd']));
			if (!is_object($cmd)) {
				continue;
			}
			$value = $cmd->execCmd();
			if (isset($window['invert']) && $window['invert'] == 1) {
				$value = ($value == 0) ? 1 : 0;
			}
			if ($value == 1) {
				$this->log->debug('[windowClose] ' . __('Fenêtre ouverte, je ne fais rien', __FILE__) . ' : ' . $window['cmd']);
				return;
			}
			$restartTime = (isset($window['restartTime']) && $window['restartTime'] != '') ? $window['restartTime'] * 60 : 0;
			if ((strtotime($this->thermostat->getCache('window::close::' . $cmd->getId() . '::datetime')) + $restartTime - 1) > strtotime('now')) {
				$this->log->debug('[windowClose] ' . __('Fenêtre fermée depuis trop peu de temps, je ne fais rien', __FILE__) . ' : ' . $window['cmd'] . ' => ' . $this->thermostat->getCache('window::close::' . $cmd->getId() . '::datetime') . '+' . $restartTime . 's');
				return;
			}
		}
		$this->log->debug('[windowClose] ' . __('Toutes les fenêtres sont fermées, je relance le chauffage', __FILE__));
		$this->thermostat->getCmd(null, 'status')->event(__('Calcul', __FILE__));
		$this->thermostat->setCache('window::state::open', -1);
		$this->thermostat->runEngine();
	}

	public function alert() {
		if (
			$this->thermostat->getConfiguration('window_alertIfOpenMoreThan') != ''
			&& $this->thermostat->getConfiguration('window_alertIfOpenMoreThan') > 0
			&& $this->thermostat->getCache('window::state::open', -1) != -1
			&& (strtotime('now') - $this->thermostat->getCache('window::state::open', -1)) > ($this->thermostat->getConfiguration('window_alertIfOpenMoreThan') * 60)
			&& $this->thermostat->getCmd(null, 'status')->execCmd() == __('Suspendu', __FILE__)
		) {
			if ($this->thermostat->getCache('alertSendForWindow', 0) != 1) {
				$this->log->error(__("Attention le thermostat est suspendu à cause d'une fenêtre ouverte depuis", __FILE__) . ' : ' .  ((strtotime('now') - $this->thermostat->getCache('window::state::open', -1)) / 60) . __('minutes', __FILE__));
				$this->thermostat->setCache('alertSendForWindow', 1);
			}
		} else {
			if ($this->thermostat->getCache('alertSendForWindow', 0) != 0) {
				$this->thermostat->setCache('alertSendForWindow', 0);
			}
		}
	}
}
