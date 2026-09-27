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
use Jeedom\Plugin\Thermostat\Domain\Translator;

class Commands {

	/** @var \thermostat */
	private $thermostat;
	/** @var Scheduler */
	private $scheduler;
	/** @var Translator */
	private $translator;

	public function __construct(\thermostat $_thermostat, Scheduler $_scheduler, Translator $_translator) {
		$this->thermostat = $_thermostat;
		$this->scheduler = $_scheduler;
		$this->translator = $_translator;
	}

	/**
	 * @param string $_logicalId
	 * @param string $_type
	 * @param string $_subType
	 * @param (callable(\thermostatCmd): void)|null $_onCreate
	 * @return \cmd
	 */
	private function upsertCmd($_logicalId, $_type, $_subType, $_onCreate = null) {
		$cmd = $this->thermostat->getCmd(null, $_logicalId);
		if (!is_object($cmd)) {
			$cmd = new \thermostatCmd();
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

	/**
	 * @param string $_expression
	 * @return string|null
	 */
	private function firstInfoCmdId($_expression) {
		preg_match_all("/#([0-9]*)#/", $_expression, $matches);
		foreach ($matches[1] as $cmd_id) {
			if (is_numeric($cmd_id)) {
				$cmd = \cmd::byId($cmd_id);
				if (is_object($cmd) && $cmd->getType() == 'info') {
					return $cmd_id;
				}
			}
		}
		return null;
	}

	/**
	 * @return void
	 */
	public function define() {
		$order = $this->upsertCmd(LogicalId::ORDER, 'info', 'numeric', function ($cmd) {
			$cmd->setIsVisible(0);
			$cmd->setUnite('°C');
			$cmd->setName($this->translator->translate('{{Consigne}}'));
			$cmd->setConfiguration('historizeMode', 'none');
			$cmd->setIsHistorized(1);
		});
		$order->setGeneric_type('THERMOSTAT_SETPOINT');
		$order->setConfiguration('maxValue', $this->thermostat->getConfiguration(Key::ORDER_MAX));
		$order->setConfiguration('minValue', $this->thermostat->getConfiguration(Key::ORDER_MIN));
		$order->save();

		$thermostat = $this->upsertCmd(LogicalId::THERMOSTAT, 'action', 'slider', function ($cmd) {
			$cmd->setUnite('°C');
			$cmd->setName($this->translator->translate('{{Thermostat}}'));
			$cmd->setIsVisible(1);
			$cmd->setTemplate('dashboard', 'button');
			$cmd->setTemplate('mobile', 'button');
		});
		$thermostat->setGeneric_type('THERMOSTAT_SET_SETPOINT');
		$thermostat->setConfiguration('maxValue', $this->thermostat->getConfiguration(Key::ORDER_MAX));
		$thermostat->setConfiguration('minValue', $this->thermostat->getConfiguration(Key::ORDER_MIN));
		$thermostat->setValue($order->getId());
		$thermostat->save();

		$status = $this->upsertCmd(LogicalId::STATUS, 'info', 'string', function ($cmd) {
			$cmd->setIsVisible(1);
			$cmd->setName($this->translator->translate('{{Statut}}'));
		});
		$status->setGeneric_type('THERMOSTAT_STATE_NAME');
		$status->save();

		$actif = $this->upsertCmd(LogicalId::ACTIVE, 'info', 'binary', function ($cmd) {
			$cmd->setName($this->translator->translate('{{Actif}}'));
			$cmd->setIsVisible(0);
			$cmd->setIsHistorized(1);
		});
		$actif->setGeneric_type('THERMOSTAT_STATE');
		$actif->save();

		$lockState = $this->upsertCmd(LogicalId::LOCK_STATE, 'info', 'binary', function ($cmd) {
			$cmd->setTemplate('dashboard', 'lock');
			$cmd->setTemplate('mobile', 'lock');
			$cmd->setName($this->translator->translate('{{Verrouillage}}'));
			$cmd->setIsVisible(0);
		});
		$lockState->setGeneric_type('THERMOSTAT_LOCK');
		$lockState->save();

		foreach (array(LogicalId::LOCK => 'THERMOSTAT_SET_LOCK', LogicalId::UNLOCK => 'THERMOSTAT_SET_UNLOCK') as $logicalId => $genericType) {
			$cmd = $this->upsertCmd($logicalId, 'action', 'other', function ($cmd) use ($logicalId) {
				$cmd->setTemplate('dashboard', 'lock');
				$cmd->setTemplate('mobile', 'lock');
				$cmd->setName($logicalId);
				$cmd->setOrder(1);
			});
			$cmd->setGeneric_type($genericType);
			$cmd->setIsVisible(($this->thermostat->getConfiguration(Key::HIDE_LOCK_CMD) == 1) ? 0 : 1);
			$cmd->setValue($lockState->getId());
			$cmd->save();
		}

		$temperatures = array(
			LogicalId::TEMPERATURE => array(Key::TEMPERATURE_INDOOR, $this->translator->translate('{{Température}}'), 'THERMOSTAT_TEMPERATURE'),
			LogicalId::TEMPERATURE_OUTDOOR => array(Key::TEMPERATURE_OUTDOOR, $this->translator->translate('{{Température extérieure}}'), 'THERMOSTAT_TEMPERATURE_OUTDOOR'),
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

		foreach (array(LogicalId::OFFSET_HEAT => $this->translator->translate('{{Offset chauffage}}'), LogicalId::OFFSET_COOL => $this->translator->translate('{{Offset froid}}')) as $logicalId => $name) {
			$cmd = $this->upsertCmd($logicalId, 'action', 'slider', function ($cmd) use ($name) {
				$cmd->setName($name);
				$cmd->setIsVisible(0);
			});
			$cmd->setConfiguration('minValue', -100);
			$cmd->save();
		}

		foreach (array(LogicalId::HEAT_ONLY => $this->translator->translate('{{Chauffage seulement}}'), LogicalId::COOL_ONLY => $this->translator->translate('{{Climatisation seulement}}')) as $logicalId => $name) {
			$cmd = $this->upsertCmd($logicalId, 'action', 'other', function ($cmd) use ($name) {
				$cmd->setName($name);
				$cmd->setIsVisible(0);
			});
			$cmd->save();
		}

		$allAllow = $this->upsertCmd(LogicalId::ALL_ALLOW, 'action', 'other');
		$allAllow->setName($this->translator->translate('{{Tout autorisé}}'));
		$allAllow->setIsVisible(0);
		$allAllow->save();

		$mode = $this->upsertCmd(LogicalId::MODE, 'info', 'string', function ($cmd) {
			$cmd->setName($this->translator->translate('{{Mode}}'));
			$cmd->setIsVisible(1);
		});
		$mode->setGeneric_type('THERMOSTAT_MODE');
		$mode->save();

		$off = $this->upsertCmd(LogicalId::OFF, 'action', 'other', function ($cmd) {
			$cmd->setIsVisible(1);
			$cmd->setName($this->translator->translate('{{Off}}'));
		});
		$off->setGeneric_type('THERMOSTAT_SET_MODE');
		$off->setValue($mode->getId());
		$off->save();

		$coefficients = array(
			LogicalId::COEFF_INDOOR_HEAT => $this->translator->translate('{{Coefficient chaud}}'),
			LogicalId::COEFF_OUTDOOR_HEAT => $this->translator->translate('{{Isolation chaud}}'),
			LogicalId::COEFF_INDOOR_COOL => $this->translator->translate('{{Coefficient froid}}'),
			LogicalId::COEFF_OUTDOOR_COOL => $this->translator->translate('{{Isolation froid}}'),
			LogicalId::SMART_START_FACTOR => $this->translator->translate('{{Anticipation smart start}}'),
		);
		if ($this->thermostat->getConfiguration(Key::ENGINE, EngineType::TEMPORAL) == EngineType::TEMPORAL) {
			$deltaOrder = $this->upsertCmd(LogicalId::DELTA_ORDER, 'action', 'slider', function ($cmd) {
				$cmd->setUnite('°C');
				$cmd->setName($this->translator->translate('{{Delta consigne}}'));
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
			foreach (array_merge(array(LogicalId::DELTA_ORDER), array_keys($coefficients)) as $logicalId) {
				$cmd = $this->thermostat->getCmd(null, $logicalId);
				if (is_object($cmd)) {
					$cmd->remove();
				}
			}
		}

		if ($this->thermostat->getConfiguration(Key::CONSUMPTION) != '') {
			$performance = $this->upsertCmd(LogicalId::PERFORMANCE, 'info', 'numeric', function ($cmd) {
				$cmd->setIsVisible(0);
				$cmd->setName($this->translator->translate('{{Performance}}'));
			});
			$performance->setIsHistorized(1);
			$performance->setDisplay('groupingType', 'high::day');
			$performance->setConfiguration('historizeMode', 'max');
			$performance->setUnite('kWh/DJU');
			$performance->save();
			preg_match_all("/#([0-9]*)#/", $this->thermostat->getConfiguration(Key::CONSUMPTION), $matches);
			$matches[1][] = $this->thermostat->getCmd(null, LogicalId::TEMPERATURE_OUTDOOR)->getId();
			$this->scheduler->listen(Callback::UPDATE_PERFORMANCE, $matches[1]);
		}

		if ($this->thermostat->getConfiguration(Key::CUSTOM_CMD, '') != '') {
			$customCmd = $this->thermostat->getCmd(null, LogicalId::CUSTOM_CMD);
			if (!is_object($customCmd)) {
				$customCmd = new \thermostatCmd();
				$customCmd->setTemplate('dashboard', 'line');
				$customCmd->setTemplate('mobile', 'line');
				$customCmd->setIsVisible(1);
			}
			$customCmd->setEqLogic_id($this->thermostat->getId());
			$customCmd->setLogicalId(LogicalId::CUSTOM_CMD);
			$customCmd->setType('info');
			$cmd_id = $this->firstInfoCmdId($this->thermostat->getConfiguration(Key::CUSTOM_CMD));
			if ($cmd_id !== null) {
				$cmd = \cmd::byId($cmd_id);
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
		} else if (is_object($customCmd = $this->thermostat->getCmd(null, LogicalId::CUSTOM_CMD))) {
			$customCmd->remove();
		}

		$knowModes = array();
		if (is_array($this->thermostat->getConfiguration(Key::MODES))) {
			foreach ($this->thermostat->getConfiguration(Key::MODES) as $existingMode) {
				$knowModes[$existingMode['name']] = $existingMode;
			}
		}
		foreach ($this->thermostat->getCmd() as $cmd) {
			if ($cmd->getLogicalId() == LogicalId::MODE_ACTION) {
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
			$modeAction = new \thermostatCmd();
			$modeAction->setEqLogic_id($this->thermostat->getId());
			$modeAction->setName($knowMode['name']);
			$modeAction->setType('action');
			$modeAction->setSubType('other');
			$modeAction->setLogicalId(LogicalId::MODE_ACTION);
			if (isset($knowMode['isVisible'])) {
				$modeAction->setIsVisible($knowMode['isVisible']);
			}
			$modeAction->setGeneric_type('THERMOSTAT_SET_MODE');
			$modeAction->setValue($mode->getId());
			$modeAction->save();
		}
	}

	/**
	 * @return void
	 */
	public function definePower() {
		$power = $this->upsertCmd(LogicalId::POWER, 'info', 'numeric', function ($cmd) {
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
			$cmd->setName($this->translator->translate('{{Puissance}}'));
			$cmd->setIsVisible(1);
			$cmd->setIsHistorized(1);
			$cmd->setConfiguration('historizeMode', 'none');
		});
		$power->setUnite('%');
		$power->save();
	}

	/**
	 * @return void
	 */
	public function removePower() {
		$power = $this->thermostat->getCmd(null, LogicalId::POWER);
		if (is_object($power)) {
			$power->remove();
		}
	}
}
