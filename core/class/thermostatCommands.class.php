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

class thermostatCommands {

	private $thermostat;
	private $scheduler;

	public function __construct($_thermostat, thermostatScheduler $_scheduler) {
		$this->thermostat = $_thermostat;
		$this->scheduler = $_scheduler;
	}

	private function upsertCmd($_logicalId, $_type, $_subType, $_onCreate = null) {
		$cmd = $this->thermostat->getCmd(null, $_logicalId);
		if (!is_object($cmd)) {
			$cmd = new thermostatCmd();
			if ($_onCreate !== null) {
				$_onCreate($cmd);
			}
		}
		$cmd->setEqLogic_id($this->thermostat->getId());
		$cmd->setType($_type);
		$cmd->setSubType($_subType);
		$cmd->setLogicalId($_logicalId);
		return $cmd;
	}

	private function firstInfoCmdId($_expression) {
		preg_match_all("/#([0-9]*)#/", $_expression, $matches);
		foreach ($matches[1] as $cmd_id) {
			if (is_numeric($cmd_id)) {
				$cmd = cmd::byId($cmd_id);
				if (is_object($cmd) && $cmd->getType() == 'info') {
					return $cmd_id;
				}
			}
		}
		return null;
	}

	public function define() {
		$order = $this->upsertCmd('order', 'info', 'numeric', function ($cmd) {
			$cmd->setIsVisible(0);
			$cmd->setUnite('°C');
			$cmd->setName(__('Consigne', __FILE__));
			$cmd->setConfiguration('historizeMode', 'none');
			$cmd->setIsHistorized(1);
		});
		$order->setGeneric_type('THERMOSTAT_SETPOINT');
		$order->setConfiguration('maxValue', $this->thermostat->getConfiguration('order_max'));
		$order->setConfiguration('minValue', $this->thermostat->getConfiguration('order_min'));
		$order->save();

		$thermostat = $this->upsertCmd('thermostat', 'action', 'slider', function ($cmd) {
			$cmd->setUnite('°C');
			$cmd->setName(__('Thermostat', __FILE__));
			$cmd->setIsVisible(1);
			$cmd->setTemplate('dashboard', 'button');
			$cmd->setTemplate('mobile', 'button');
		});
		$thermostat->setGeneric_type('THERMOSTAT_SET_SETPOINT');
		$thermostat->setConfiguration('maxValue', $this->thermostat->getConfiguration('order_max'));
		$thermostat->setConfiguration('minValue', $this->thermostat->getConfiguration('order_min'));
		$thermostat->setValue($order->getId());
		$thermostat->save();

		$status = $this->upsertCmd('status', 'info', 'string', function ($cmd) {
			$cmd->setIsVisible(1);
			$cmd->setName(__('Statut', __FILE__));
		});
		$status->setGeneric_type('THERMOSTAT_STATE_NAME');
		$status->save();

		$actif = $this->upsertCmd('actif', 'info', 'binary', function ($cmd) {
			$cmd->setName(__('Actif', __FILE__));
			$cmd->setIsVisible(0);
			$cmd->setIsHistorized(1);
		});
		$actif->setGeneric_type('THERMOSTAT_STATE');
		$actif->save();

		$lockState = $this->upsertCmd('lock_state', 'info', 'binary', function ($cmd) {
			$cmd->setTemplate('dashboard', 'lock');
			$cmd->setTemplate('mobile', 'lock');
			$cmd->setName(__('Verrouillage', __FILE__));
			$cmd->setIsVisible(0);
		});
		$lockState->setGeneric_type('THERMOSTAT_LOCK');
		$lockState->save();

		foreach (array('lock' => 'THERMOSTAT_SET_LOCK', 'unlock' => 'THERMOSTAT_SET_UNLOCK') as $logicalId => $genericType) {
			$cmd = $this->upsertCmd($logicalId, 'action', 'other', function ($cmd) use ($logicalId) {
				$cmd->setTemplate('dashboard', 'lock');
				$cmd->setTemplate('mobile', 'lock');
				$cmd->setName($logicalId);
				$cmd->setOrder(1);
			});
			$cmd->setGeneric_type($genericType);
			$cmd->setIsVisible(($this->thermostat->getConfiguration('hideLockCmd') == 1) ? 0 : 1);
			$cmd->setValue($lockState->getId());
			$cmd->save();
		}

		$temperatures = array(
			'temperature' => array('temperature_indoor', __('Température', __FILE__), 'THERMOSTAT_TEMPERATURE'),
			'temperature_outdoor' => array('temperature_outdoor', __('Température extérieure', __FILE__), 'THERMOSTAT_TEMPERATURE_OUTDOOR'),
		);
		foreach ($temperatures as $logicalId => $definition) {
			list($configurationKey, $name, $genericType) = $definition;
			$temperature = $this->upsertCmd($logicalId, 'info', 'numeric', function ($cmd) use ($name) {
				$cmd->setTemplate('dashboard', 'line');
				$cmd->setTemplate('mobile', 'line');
				$cmd->setName($name);
				$cmd->setIsVisible(1);
				$cmd->setIsHistorized(1);
			});
			$temperature->setUnite('°C');
			$cmd_id = $this->firstInfoCmdId($this->thermostat->getConfiguration($configurationKey));
			$temperature->setValue(($cmd_id === null) ? '' : '#' . $cmd_id . '#');
			$temperature->setGeneric_type($genericType);
			$temperature->save();
			if (!is_numeric($temperature->execCmd()) || $temperature->execCmd() == '') {
				$temperature->event($temperature->execute());
			}
		}

		foreach (array('offset_heat' => __('Offset chauffage', __FILE__), 'offset_cool' => __('Offset froid', __FILE__)) as $logicalId => $name) {
			$cmd = $this->upsertCmd($logicalId, 'action', 'slider', function ($cmd) use ($name) {
				$cmd->setName($name);
				$cmd->setIsVisible(0);
			});
			$cmd->setConfiguration('minValue', -100);
			$cmd->save();
		}

		foreach (array('heat_only' => __('Chauffage seulement', __FILE__), 'cool_only' => __('Climatisation seulement', __FILE__)) as $logicalId => $name) {
			$cmd = $this->upsertCmd($logicalId, 'action', 'other', function ($cmd) use ($name) {
				$cmd->setName($name);
				$cmd->setIsVisible(0);
			});
			$cmd->save();
		}

		$allAllow = $this->upsertCmd('all_allow', 'action', 'other');
		$allAllow->setName(__('Tout autorisé', __FILE__));
		$allAllow->setIsVisible(0);
		$allAllow->save();

		$mode = $this->upsertCmd('mode', 'info', 'string', function ($cmd) {
			$cmd->setName(__('Mode', __FILE__));
			$cmd->setIsVisible(1);
		});
		$mode->setGeneric_type('THERMOSTAT_MODE');
		$mode->save();

		$off = $this->upsertCmd('off', 'action', 'other', function ($cmd) {
			$cmd->setIsVisible(1);
			$cmd->setName(__('Off', __FILE__));
		});
		$off->setGeneric_type('THERMOSTAT_SET_MODE');
		$off->setValue($mode->getId());
		$off->save();

		$coefficients = array(
			'coeff_indoor_heat' => __('Coefficient chaud', __FILE__),
			'coeff_outdoor_heat' => __('Isolation chaud', __FILE__),
			'coeff_indoor_cool' => __('Coefficient froid', __FILE__),
			'coeff_outdoor_cool' => __('Isolation froid', __FILE__),
			'smart_start_factor' => __('Anticipation smart start', __FILE__),
		);
		if ($this->thermostat->getConfiguration('engine', 'temporal') == 'temporal') {
			$deltaOrder = $this->upsertCmd('deltaOrder', 'action', 'slider', function ($cmd) {
				$cmd->setUnite('°C');
				$cmd->setName(__('Delta consigne', __FILE__));
				$cmd->setIsVisible(0);
			});
			$deltaOrder->setConfiguration('maxValue', 5);
			$deltaOrder->setConfiguration('minValue', 0);
			$deltaOrder->save();

			foreach ($coefficients as $logicalId => $name) {
				$cmd = $this->upsertCmd($logicalId, 'info', 'numeric', function ($cmd) use ($name) {
					$cmd->setName($name);
					$cmd->setIsVisible(0);
					$cmd->setIsHistorized(1);
				});
				$cmd->save();
			}
		} else {
			foreach (array_merge(array('deltaOrder'), array_keys($coefficients)) as $logicalId) {
				$cmd = $this->thermostat->getCmd(null, $logicalId);
				if (is_object($cmd)) {
					$cmd->remove();
				}
			}
		}

		if ($this->thermostat->getConfiguration('consumption') != '') {
			$performance = $this->upsertCmd('performance', 'info', 'numeric', function ($cmd) {
				$cmd->setIsVisible(0);
				$cmd->setName(__('Performance', __FILE__));
			});
			$performance->setIsHistorized(1);
			$performance->setDisplay('groupingType', 'high::day');
			$performance->setConfiguration('historizeMode', 'max');
			$performance->setUnite('kWh/DJU');
			$performance->save();
			preg_match_all("/#([0-9]*)#/", $this->thermostat->getConfiguration('consumption'), $matches);
			$matches[1][] = $this->thermostat->getCmd(null, 'temperature_outdoor')->getId();
			$this->scheduler->listen('updatePerformance', $matches[1]);
		}

		if ($this->thermostat->getConfiguration('customCmd', '') != '') {
			$customCmd = $this->thermostat->getCmd(null, 'customCmd');
			if (!is_object($customCmd)) {
				$customCmd = new thermostatCmd();
				$customCmd->setTemplate('dashboard', 'line');
				$customCmd->setTemplate('mobile', 'line');
				$customCmd->setIsVisible(1);
			}
			$customCmd->setEqLogic_id($this->thermostat->getId());
			$customCmd->setLogicalId('customCmd');
			$customCmd->setType('info');
			$cmd_id = $this->firstInfoCmdId($this->thermostat->getConfiguration('customCmd'));
			if ($cmd_id !== null) {
				$cmd = cmd::byId($cmd_id);
				$customCmd->setValue('#' . $cmd_id . '#');
				$customCmd->setSubType($cmd->getSubType());
				$customCmd->setName($cmd->getName());
				$customCmd->setUnite($cmd->getUnite());
				$customCmd->setGeneric_type($cmd->getGeneric_type());
				$customCmd->save();
			}
			if ($customCmd->execCmd() == '') {
				$customCmd->event($customCmd->execute());
			}
		} else if (is_object($customCmd = $this->thermostat->getCmd(null, 'customCmd'))) {
			$customCmd->remove();
		}

		$knowModes = array();
		if (is_array($this->thermostat->getConfiguration('existingMode'))) {
			foreach ($this->thermostat->getConfiguration('existingMode') as $existingMode) {
				$knowModes[$existingMode['name']] = $existingMode;
			}
		}
		foreach ($this->thermostat->getCmd() as $cmd) {
			if ($cmd->getLogicalId() == 'modeAction') {
				if (isset($knowModes[$cmd->getName()])) {
					$cmd->setGeneric_type('THERMOSTAT_SET_MODE');
					if (isset($knowModes[$cmd->getName()]['isVisible'])) {
						$cmd->setIsVisible($knowModes[$cmd->getName()]['isVisible']);
					}
					$cmd->setValue($mode->getId());
					$cmd->save();
					unset($knowModes[$cmd->getName()]);
				} else {
					$cmd->remove();
				}
			}
		}
		foreach ($knowModes as $knowMode) {
			$modeAction = new thermostatCmd();
			$modeAction->setEqLogic_id($this->thermostat->getId());
			$modeAction->setName($knowMode['name']);
			$modeAction->setType('action');
			$modeAction->setSubType('other');
			$modeAction->setLogicalId('modeAction');
			if (isset($knowMode['isVisible'])) {
				$modeAction->setIsVisible($knowMode['isVisible']);
			}
			$modeAction->setGeneric_type('THERMOSTAT_SET_MODE');
			$modeAction->setValue($mode->getId());
			$modeAction->save();
		}
	}

	public function definePower() {
		$power = $this->upsertCmd('power', 'info', 'numeric', function ($cmd) {
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
			$cmd->setName(__('Puissance', __FILE__));
			$cmd->setIsVisible(1);
			$cmd->setIsHistorized(1);
			$cmd->setConfiguration('historizeMode', 'none');
		});
		$power->setUnite('%');
		$power->save();
	}

	public function removePower() {
		$power = $this->thermostat->getCmd(null, 'power');
		if (is_object($power)) {
			$power->remove();
		}
	}
}
