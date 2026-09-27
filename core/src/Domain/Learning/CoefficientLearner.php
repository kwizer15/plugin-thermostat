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

namespace Jeedom\Plugin\Thermostat\Domain\Learning;

use Jeedom\Plugin\Thermostat\Domain\Clock;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Key;
use Jeedom\Plugin\Thermostat\Domain\HeatingAction;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class CoefficientLearner {

	/** @var Settings */
	private $settings;
	/** @var CycleMemory */
	private $memory;
	/** @var Log */
	private $log;
	/** @var Translator */
	private $translator;
	/** @var Clock */
	private $clock;

	public function __construct(Settings $_settings, CycleMemory $_memory, Log $_log, Translator $_translator, Clock $_clock) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->log = $_log;
		$this->translator = $_translator;
		$this->clock = $_clock;
	}

	/**
	 * @param scalar|null $_temp_in
	 * @param scalar|null $_temp_out
	 * @return void
	 */
	public function learn($_temp_in, $_temp_out) {
		if ($this->memory->consecutiveFailures() >= 3 || !$this->settings->autolearn() || strtotime($this->settings->cycleEndDate()) >= $this->clock->now()) {
			return;
		}
		$this->log->debug($this->translator->translate('{{Démarre auto-apprentissage}}'));
		$lastPower = $this->memory->lastPower();
		if ($lastPower >= 100 || $lastPower <= 0) {
			return;
		}
		$lastState = $this->memory->lastState();
		$lastOrder = $this->memory->lastOrder();
		$lastTempIn = $this->memory->lastTempIn();
		$this->log->debug('Last power ok, check what I have to learn, last state : ' . $lastState);
		if ($lastState == HeatingAction::HEAT) {
			$this->log->debug('Last state is heat');
			if ($_temp_in > $lastTempIn && $lastOrder > $lastTempIn) {
				$this->log->debug('Last temps in < at current temp in');
				$coeff = $this->learnCoefficient(Key::COEFF_INDOOR_HEAT, $this->settings->coefficient(Key::COEFF_INDOOR_HEAT) * (($lastOrder - $lastTempIn) / ($_temp_in - $lastTempIn)));
				$this->log->debug('New coeff heat indoor : ' . $coeff);
			} else if ($_temp_out < $lastOrder) {
				$this->log->debug('Learn outdoor heat');
				$coeff = $this->learnCoefficient(Key::COEFF_OUTDOOR_HEAT, $this->settings->coefficient(Key::COEFF_INDOOR_HEAT) * (($lastOrder - $_temp_in) / ($lastOrder - $_temp_out)) + $this->settings->coefficient(Key::COEFF_OUTDOOR_HEAT));
				$this->log->debug('New coeff outdoor heat: ' . $coeff);
			}
		}
		if ($lastState == HeatingAction::COOL) {
			$this->log->debug('Last state is cool');
			if ($_temp_in < $lastTempIn && $lastOrder < $lastTempIn) {
				$this->log->debug('Last temps in > at current temp in');
				$coeff = $this->learnCoefficient(Key::COEFF_INDOOR_COOL, $this->settings->coefficient(Key::COEFF_INDOOR_COOL) * (($lastTempIn - $lastOrder) / ($lastTempIn - $_temp_in)));
				$this->log->debug('New coeff cool indoor : ' . $coeff);
			} else if ($_temp_out > $lastOrder) {
				$this->log->debug('Learn outdoor cool');
				$coeff = $this->learnCoefficient(Key::COEFF_OUTDOOR_COOL, $this->settings->coefficient(Key::COEFF_INDOOR_COOL) * (($lastOrder - $_temp_in) / ($lastOrder - $_temp_out)) + $this->settings->coefficient(Key::COEFF_OUTDOOR_COOL));
				$this->log->debug('New coeff outdoor cool : ' . $coeff);
			}
		}
	}

	/**
	 * @param string $_key
	 * @param int|float $_measured
	 * @return int|float
	 */
	private function learnCoefficient($_key, $_measured) {
		$count = $this->settings->learnedCount($_key);
		$coeff = ($this->settings->coefficient($_key) * $count + $_measured) / ($count + 1);
		if ($coeff < 0 || !is_numeric($coeff)) {
			$coeff = 0;
		}
		$this->settings->storeCoefficient($_key, round($coeff, 2), min($count + 1, 50));
		return $coeff;
	}
}
