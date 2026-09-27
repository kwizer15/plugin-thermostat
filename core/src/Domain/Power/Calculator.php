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

namespace Jeedom\Plugin\Thermostat\Domain\Power;

use Jeedom\Plugin\Thermostat\Domain\HeatingAction;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class Calculator {

	/** @var Settings */
	private $settings;
	/** @var Memory */
	private $memory;
	/** @var Log */
	private $log;
	/** @var Translator */
	private $translator;

	public function __construct(Settings $_settings, Memory $_memory, Log $_log, Translator $_translator) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->log = $_log;
		$this->translator = $_translator;
	}

	/**
	 * @param scalar|null $_consigne
	 * @param scalar|null $_tempIn
	 * @param scalar|null $_tempOut
	 * @param bool $_allowOverfull
	 * @return array{power: int|float, direction: int}
	 */
	public function compute($_consigne, $_tempIn, $_tempOut, $_allowOverfull = false) {
		$temp_out = $_tempOut;
		$temp_in = $_tempIn;
		if (!is_numeric($temp_out)) {
			$this->log->debug($this->translator->translate('{{Attention température extérieure erronée}}') . ' : ' . $temp_out);
			$temp_out = $_consigne;
		}

		$this->log->debug($this->translator->translate('{{Température intérieure}}') . ' : ' . $temp_in . ' - ' . $this->translator->translate('{{Température extérieure}}') . ' : ' . $temp_out . ' - ' . $this->translator->translate('{{Consigne}}') . ' : ' . $_consigne);
		$diff_in = $_consigne - $temp_in;
		$diff_out = $_consigne - $temp_out;
		$direction = ($_consigne > $temp_in) ? +1 : -1;
		if ($direction < 0 && (($temp_in < ($_consigne + 0.5) && $this->memory->lastState() == HeatingAction::HEAT) || ($_consigne - $temp_out) > $this->settings->directionDeltaHeat())) {
			$direction = +1;
		}
		if ($direction > 0 && (($temp_in > ($_consigne - 0.5) && $this->memory->lastState() == HeatingAction::COOL) || ($_consigne - $temp_out) < $this->settings->directionDeltaCool())) {
			$direction = -1;
		}
		$this->log->debug($this->translator->translate('{{Direction}}') . ' : ' . $direction);
		if ($temp_in >= ($_consigne + 1.5) && $direction == 1) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->log->debug($this->translator->translate('{{La température est supérieure à la consigne de plus de 1.5°C, je ne fais rien}}'));
			}
			$this->memory->setTemperatureAlert(1);
			return array('power' => 0, 'direction' => $direction);
		}
		if ($temp_in <= ($_consigne - 1.5) && $direction == -1) {
			if ($this->memory->temperatureAlert() == 0) {
				$this->log->debug($this->translator->translate('{{La température est inférieure à la consigne de plus de 1.5°C, je ne fais rien}}'));
			}
			$this->memory->setTemperatureAlert(1);
			return array('power' => 0, 'direction' => $direction);
		}
		$this->memory->setTemperatureAlert(0);
		$coeff_out = $this->settings->coefficientOutdoor($direction);
		$coeff_in = $this->settings->coefficientIndoor($direction);
		$offset = $this->settings->offset($direction);
		$power = ($direction * $diff_in * $coeff_in) + ($direction * $diff_out * $coeff_out) + $offset;
		$this->log->debug('Power calcul : (' . $diff_in . ' * ' . $coeff_in . ') + (' . $diff_out . ' * ' . $coeff_out . ') + ' . $offset . ' = ' . $power);

		$fullCycleOffset = $this->settings->nextFullCycleOffset();
		$lastPower = $this->memory->lastPower();
		if (!$_allowOverfull && $fullCycleOffset !== null && $fullCycleOffset > 0 && $lastPower >= $this->settings->heatHotThreshold()) {
			if ($lastPower >= 100) {
				$this->log->debug($this->translator->translate('{{Cycle précédent à 100%, applique offset}}') . ' : ' . $fullCycleOffset . '%');
				$power -= $fullCycleOffset;
			} else {
				$this->log->debug($this->translator->translate('{{Cycle précédent à}}') . ' ' . $lastPower . '%, ' . $this->translator->translate('{{applique offset}}') . ' : ' . $fullCycleOffset . '%');
				$this->log->debug($this->translator->translate('{{Puissance de chauffe du cycle}}') . ' : ' . $power . '% - ' . $fullCycleOffset . '% + ' . (100 - $lastPower) . '%');
				$power -= $fullCycleOffset - (100 - $lastPower);
			}
		}
		if ($power > 100 && !$_allowOverfull) {
			$power = 100;
		}
		if ($power < 0) {
			$power = 0;
		}
		return array('power' => $power, 'direction' => $direction);
	}
}
