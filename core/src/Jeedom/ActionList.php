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

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actions;
use Jeedom\Plugin\Thermostat\Domain\Command\LogicalId;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class ActionList implements Actions {

	/** @var \thermostat */
	private $thermostat;
	/** @var Log */
	private $log;
	/** @var Translator */
	private $translator;

	public function __construct(\thermostat $_thermostat, Log $_log, Translator $_translator) {
		$this->thermostat = $_thermostat;
		$this->log = $_log;
		$this->translator = $_translator;
	}

	/**
	 * @param array{cmd: string, options?: array<string, scalar|null>} $_action
	 * @param scalar|null $_consigne
	 * @return array<string, mixed>
	 */
	public static function options($_action, $_consigne) {
		$options = array();
		if (isset($_action['options'])) {
			$options = $_action['options'];
			foreach ($options as $key => $value) {
				$options[$key] = str_replace('#slider#', $_consigne, $value);
			}
		}
		return $options;
	}

	public function execute($_actions, $_skipOwnCmds, $_extraOptions = array()) {
		$consigne = $this->thermostat->getCmd(null, LogicalId::ORDER)->execCmd();
		foreach ($_actions as $action) {
			try {
				if ($_skipOwnCmds) {
					$cmd = \cmd::byId(str_replace('#', '', $action['cmd']));
					if (is_object($cmd) && $this->thermostat->getId() == $cmd->getEqLogic_id()) {
						continue;
					}
				}
				$options = self::options($action, $consigne);
				foreach ($_extraOptions as $key => $value) {
					$options[$key] = $value;
				}
				\scenarioExpression::createAndExec('action', $action['cmd'], $options);
			} catch (\Exception $e) {
				$this->logError($action, $e);
			}
		}
	}

	public function applyMode($_actions, $_consigne) {
		$thermostatCmd = false;
		foreach ($_actions as $action) {
			try {
				$options = self::options($action, $_consigne);
				$cmd = (is_numeric(str_replace('#', '', $action['cmd']))) ? \cmd::byString($action['cmd']) : '';
				if (is_object($cmd) && $cmd->getEqLogic_id() == $this->thermostat->getId() && $cmd->getLogicalId() == LogicalId::THERMOSTAT) {
					$thermostatCmd = true;
					$this->thermostat->getCmd(null, LogicalId::ORDER)->event(\scenarioExpression::createAndExec('condition', $options['slider']));
				} else {
					\scenarioExpression::createAndExec('action', $action['cmd'], $options);
				}
			} catch (\Exception $e) {
				$this->logError($action, $e);
			}
		}
		return $thermostatCmd;
	}

	/**
	 * @param array{cmd: string, options?: array<string, scalar|null>} $_action
	 * @return void
	 */
	private function logError($_action, \Exception $_exception) {
		$this->log->error($this->translator->translate("{{Erreur lors de l'exécution de}}") . ' ' . $_action['cmd'] . '. ' . $this->translator->translate('{{Détails}}') . ' : ' . $_exception->getMessage());
	}
}
