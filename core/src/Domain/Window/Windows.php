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

namespace Jeedom\Plugin\Thermostat\Domain\Window;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Clock;
use Jeedom\Plugin\Thermostat\Domain\Display;
use Jeedom\Plugin\Thermostat\Domain\EngineRunner;
use Jeedom\Plugin\Thermostat\Domain\Log;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class Windows {

	/** @var Settings */
	private $settings;
	/** @var Memory */
	private $memory;
	/** @var Display */
	private $display;
	/** @var Sensors */
	private $sensors;
	/** @var Actuator */
	private $actuator;
	/** @var EngineRunner */
	private $engine;
	/** @var Log */
	private $log;
	/** @var StatusLabels */
	private $labels;
	/** @var Translator */
	private $translator;
	/** @var Clock */
	private $clock;
	/** @var Timer */
	private $timer;

	public function __construct(Settings $_settings, Memory $_memory, Display $_display, Sensors $_sensors, Actuator $_actuator, EngineRunner $_engine, Log $_log, StatusLabels $_labels, Translator $_translator, Clock $_clock, Timer $_timer) {
		$this->settings = $_settings;
		$this->memory = $_memory;
		$this->display = $_display;
		$this->sensors = $_sensors;
		$this->actuator = $_actuator;
		$this->engine = $_engine;
		$this->log = $_log;
		$this->labels = $_labels;
		$this->translator = $_translator;
		$this->clock = $_clock;
		$this->timer = $_timer;
	}

	/**
	 * @param array{event_id: int|string, value: scalar|null} $_option
	 * @return void
	 */
	public function handle($_option) {
		$this->log->debug($this->translator->translate("{{Détection d'un changement sur une fenêtre}}"));
		$windows = $this->settings->windows();
		foreach ($windows as $window) {
			if ('#' . $_option['event_id'] . '#' == $window['cmd']) {
				if (isset($window['invert']) && $window['invert'] == 1) {
					$_option['value'] = ($_option['value'] == 0) ? 1 : 0;
				}
				$this->log->debug($this->translator->translate('{{Fenêtre trouvée}}') . ' : ' . $this->sensors->name($window['cmd']) . ' - ' . $this->translator->translate('{{valeur}}') . ' : ' . $_option['value']);
				if ($_option['value'] == 0) {
					$this->log->debug($this->translator->translate('{{Fenêtre fermée}}'));
					$this->close($window);
				} else {
					$this->log->debug($this->translator->translate('{{Fenêtre ouverte}}'));
					$this->open($window);
				}
			}
		}
	}

	/**
	 * @param array{cmd: string, invert?: int|string, stopTime?: int|string, restartTime?: int|string} $_window
	 * @return true|null
	 */
	public function open($_window) {
		$this->log->debug('[windowOpen] => ' . json_encode($_window));
		$this->memory->setWindowState(str_replace('#', '', $_window['cmd']), 1);
		if ($this->display->isOff() || $this->display->status() == $this->labels->suspended()) {
			$this->log->debug('[windowOpen] ' . $this->translator->translate('{{Thermostat arreté ou suspendu je ne fais rien}}'));
			return;
		}
		$cmdId = str_replace('#', '', $_window['cmd']);
		$stopTime = (isset($_window['stopTime']) && $_window['stopTime'] != '') ? $_window['stopTime'] : 0;
		if (is_numeric($stopTime) && $stopTime > 0) {
			$this->log->debug('[windowOpen] ' . $this->translator->translate('{{Pause de}}') . ' ' . $stopTime . ' ' . $this->translator->translate('{{minutes}}'));
			$this->memory->setOpenedAt($cmdId, date('Y-m-d H:i:s', $this->clock->now()));
			$this->timer->schedule($cmdId, TimerPhase::OPEN, $this->clock->now() + (int) ($stopTime * 60));
			return true;
		}
		return $this->confirmOpen($_window, $this->clock->now());
	}

	/**
	 * @param int|string $_cmdId
	 * @param TimerPhase::* $_phase
	 * @return void
	 */
	public function timer($_cmdId, $_phase) {
		foreach ($this->settings->windows() as $window) {
			if ($window['cmd'] != '#' . $_cmdId . '#') {
				continue;
			}
			if ($_phase == TimerPhase::OPEN) {
				$this->confirmOpen($window, (int) strtotime($this->memory->openedAt($_cmdId)));
			} else {
				$this->resume();
			}
		}
	}

	/**
	 * @param array{cmd: string, invert?: int|string, stopTime?: int|string, restartTime?: int|string} $_window
	 * @param int $_openedAt
	 * @return true|null
	 */
	private function confirmOpen($_window, $_openedAt) {
		$reading = $this->sensors->read(str_replace('#', '', $_window['cmd']));
		if ($reading === null) {
			$this->log->debug('[windowOpen] ' . $this->translator->translate('{{Commande introuvable je ne fais rien}}'));
			return null;
		}
		$value = $reading->value();
		if (isset($_window['invert']) && $_window['invert'] == 1) {
			$value = ($value == 0) ? 1 : 0;
		}
		$this->log->debug('[windowOpen] ' . $this->translator->translate('{{Valeur commande}}') . ' : ' . $value . $this->translator->translate('{{ en date du : }}') . $reading->valueDate());
		if ($value != 1) {
			$this->log->debug('[windowOpen] ' . $this->translator->translate("{{L'ouvrant n'est plus ouvert, je ne fais rien}}"));
			return true;
		}
		if (strtotime($reading->valueDate()) > ($_openedAt + 5)) {
			$this->log->debug('[windowOpen] ' . $this->translator->translate("{{L'ouvrant à été refermé pendant la pause, je ne fais rien, refermé à}}") . ' ' . $reading->valueDate());
			return true;
		}
		$this->log->debug('[windowOpen] ' . $this->translator->translate('{{Arrêt du thermostat}}'));
		$this->display->setStatus($this->labels->suspended());
		$this->actuator->stop(false, true);
		$this->memory->setOpenSince($this->clock->now());
		return true;
	}

	/**
	 * @param array{cmd: string, invert?: int|string, stopTime?: int|string, restartTime?: int|string} $_window
	 * @return void
	 */
	public function close($_window) {
		if ($this->memory->windowState(str_replace('#', '', $_window['cmd'])) != 1) {
			$this->log->debug('[windowClose] ' . $this->translator->translate("{{Je n'ai jamais vu cette fenêtre ouverte, je ne fais rien}}"));
			return;
		}
		$this->memory->setWindowState(str_replace('#', '', $_window['cmd']), 0);
		$this->log->debug('[windowClose] => ' . json_encode($_window));
		if ($this->display->status() != $this->labels->suspended()) {
			$this->log->debug('[windowClose] ' . $this->translator->translate('{{Thermostat non suspendu je ne fais rien}}'));
			return;
		}
		$this->memory->setClosedAt(str_replace('#', '', $_window['cmd']), date('Y-m-d H:i:s', $this->clock->now()));
		$restartTime = (isset($_window['restartTime']) && $_window['restartTime'] != '') ? $_window['restartTime'] * 60 : 0;
		if ($restartTime > 0) {
			$this->log->debug('[windowClose] ' . $this->translator->translate('{{Pause de}}') . ' ' . $restartTime . 's');
			$this->timer->schedule(str_replace('#', '', $_window['cmd']), TimerPhase::CLOSE, $this->clock->now() + (int) $restartTime);
			return;
		}
		$this->resume();
	}

	/**
	 * @return void
	 */
	private function resume() {
		$windows = $this->settings->windows();
		foreach ($windows as $window) {
			$cmdId = str_replace('#', '', $window['cmd']);
			$reading = $this->sensors->read($cmdId);
			if ($reading === null) {
				continue;
			}
			$value = $reading->value();
			if (isset($window['invert']) && $window['invert'] == 1) {
				$value = ($value == 0) ? 1 : 0;
			}
			if ($value == 1) {
				$this->log->debug('[windowClose] ' . $this->translator->translate('{{Fenêtre ouverte, je ne fais rien}}') . ' : ' . $window['cmd']);
				return;
			}
			$restartTime = (isset($window['restartTime']) && $window['restartTime'] != '') ? $window['restartTime'] * 60 : 0;
			if ((strtotime($this->memory->closedAt($cmdId)) + $restartTime - 1) > $this->clock->now()) {
				$this->log->debug('[windowClose] ' . $this->translator->translate('{{Fenêtre fermée depuis trop peu de temps, je ne fais rien}}') . ' : ' . $window['cmd'] . ' => ' . $this->memory->closedAt($cmdId) . '+' . $restartTime . 's');
				return;
			}
		}
		$this->log->debug('[windowClose] ' . $this->translator->translate('{{Toutes les fenêtres sont fermées, je relance le chauffage}}'));
		$this->display->setStatus($this->labels->computing());
		$this->memory->setOpenSince(-1);
		$this->engine->run();
	}

	/**
	 * @return void
	 */
	public function alert() {
		if (
			$this->settings->windowAlertDelay() !== null
			&& $this->settings->windowAlertDelay() > 0
			&& $this->memory->openSince() != -1
			&& ($this->clock->now() - $this->memory->openSince()) > ($this->settings->windowAlertDelay() * 60)
			&& $this->display->status() == $this->labels->suspended()
		) {
			if ($this->memory->alertSent() != 1) {
				$this->log->error($this->translator->translate("{{Attention le thermostat est suspendu à cause d'une fenêtre ouverte depuis}}") . ' : ' .  (($this->clock->now() - $this->memory->openSince()) / 60) . $this->translator->translate('{{minutes}}'));
				$this->memory->setAlertSent(1);
			}
		} else {
			if ($this->memory->alertSent() != 0) {
				$this->memory->setAlertSent(0);
			}
		}
	}
}
