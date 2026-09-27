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

namespace Jeedom\Plugin\Thermostat\Domain\Engine;

use Jeedom\Plugin\Thermostat\Domain\AllowMode;
use Jeedom\Plugin\Thermostat\Domain\HeatingAction;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class HysteresisDecision {

	/** @var HysteresisSettings */
	private $settings;
	/** @var Log */
	private $log;
	/** @var StatusLabels */
	private $labels;
	/** @var Translator */
	private $translator;

	public function __construct(HysteresisSettings $_settings, Log $_log, StatusLabels $_labels, Translator $_translator) {
		$this->settings = $_settings;
		$this->log = $_log;
		$this->labels = $_labels;
		$this->translator = $_translator;
	}

	/**
	 * @param scalar|null $_temp
	 * @param scalar|null $_consigne
	 * @param scalar|null $_status
	 * @param string $_lastState
	 * @return HeatingAction::*
	 */
	public function decide($_temp, $_consigne, $_status, $_lastState) {
		$allowMode = $this->settings->allowMode();
		$threshold = $this->settings->hysteresisThreshold();
		$positive = ($this->settings->positiveHysteresis() == 1);
		$hysteresis_low = ($allowMode == AllowMode::HEAT && $positive) ? $_consigne : $_consigne - $threshold;
		$hysteresis_hight = ($allowMode == AllowMode::COOL && $positive) ? $_consigne : $_consigne + $threshold;
		$this->log->debug($this->translator->translate('{{Calcul}}') . ' => ' . $this->translator->translate('{{consigne}}') . ' : ' . $_consigne . ' hysteresis_low : ' . $hysteresis_low . ' hysteresis_hight : ' . $hysteresis_hight . ' temp : ' . $_temp . ' ' . $this->translator->translate('{{état précédent}}') . ' : ' . $_lastState);
		$action = HeatingAction::NONE;
		if ($_temp < $hysteresis_low) {
			$action = HeatingAction::HEAT;
		}
		if ($_temp > $hysteresis_hight) {
			$action = HeatingAction::COOL;
		}
		if ($action == HeatingAction::HEAT && $_lastState == HeatingAction::COOL && ($_consigne - 2 * $threshold) < $_temp) {
			$action = HeatingAction::NONE;
		}
		if ($action == HeatingAction::COOL && $_lastState == HeatingAction::HEAT && ($_consigne + 2 * $threshold) > $_temp) {
			$action = HeatingAction::NONE;
		}
		if ($_status == $this->labels->heating() && $_temp > $hysteresis_hight) {
			$action = HeatingAction::STOP;
		}
		if ($_status == $this->labels->cooling() && $_temp < ($_consigne - $threshold)) {
			$action = HeatingAction::STOP;
		}
		if (($action == HeatingAction::COOL || $action == HeatingAction::HEAT) && $allowMode != AllowMode::ALL && $allowMode != $action) {
			$action = HeatingAction::NONE;
		}
		return $action;
	}
}
