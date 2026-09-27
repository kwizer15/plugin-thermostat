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

namespace Jeedom\Plugin\Thermostat;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Command\Handler;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Configuration;
use Jeedom\Plugin\Thermostat\Domain\Cycle\Planner;
use Jeedom\Plugin\Thermostat\Domain\Engine\HysteresisDecision;
use Jeedom\Plugin\Thermostat\Domain\Engine\HysteresisEngine;
use Jeedom\Plugin\Thermostat\Domain\Engine\TemporalEngine;
use Jeedom\Plugin\Thermostat\Domain\Learning\CoefficientLearner;
use Jeedom\Plugin\Thermostat\Domain\Power\Calculator;
use Jeedom\Plugin\Thermostat\Domain\SensorWatch\SensorWatch;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\SmartStart;
use Jeedom\Plugin\Thermostat\Domain\Statistics\Statistics;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Window\Windows;
use Jeedom\Plugin\Thermostat\Jeedom\ActionList;
use Jeedom\Plugin\Thermostat\Jeedom\Calendar;
use Jeedom\Plugin\Thermostat\Jeedom\CommandLookup;
use Jeedom\Plugin\Thermostat\Jeedom\Commands;
use Jeedom\Plugin\Thermostat\Jeedom\Controls;
use Jeedom\Plugin\Thermostat\Jeedom\Display;
use Jeedom\Plugin\Thermostat\Jeedom\EngineRunner;
use Jeedom\Plugin\Thermostat\Jeedom\Evaluator;
use Jeedom\Plugin\Thermostat\Jeedom\History;
use Jeedom\Plugin\Thermostat\Jeedom\Log;
use Jeedom\Plugin\Thermostat\Jeedom\Memory;
use Jeedom\Plugin\Thermostat\Jeedom\Persistence;
use Jeedom\Plugin\Thermostat\Jeedom\Scheduler;
use Jeedom\Plugin\Thermostat\Jeedom\Sensors;
use Jeedom\Plugin\Thermostat\Jeedom\Settings;
use Jeedom\Plugin\Thermostat\Jeedom\SystemClock;
use Jeedom\Plugin\Thermostat\Jeedom\Translator;
use Jeedom\Plugin\Thermostat\Jeedom\WindowSensors;

class Assembly {

	/** @var \thermostat */
	private $thermostat;

	public function __construct(\thermostat $_thermostat) {
		$this->thermostat = $_thermostat;
	}

	public function log(): Log {
		return new Log($this->thermostat->getHumanName());
	}

	/**
	 * @param class-string $_class
	 */
	public function translator($_class): Translator {
		return new Translator(dirname(__FILE__) . '/' . str_replace('\\', '/', substr($_class, strlen(__NAMESPACE__) + 1)) . '.php');
	}

	public function statusLabels(): StatusLabels {
		return new StatusLabels($this->translator(StatusLabels::class));
	}

	public function commandLookup(): CommandLookup {
		return new CommandLookup($this->thermostat, $this->translator(CommandLookup::class));
	}

	public function clock(): SystemClock {
		return new SystemClock();
	}

	public function settings(): Settings {
		return new Settings($this->thermostat);
	}

	public function memory(): Memory {
		return new Memory($this->thermostat);
	}

	public function sensors(): Sensors {
		return new Sensors($this->commandLookup());
	}

	public function evaluator(): Evaluator {
		return new Evaluator();
	}

	public function calendar(): Calendar {
		return new Calendar($this->thermostat, $this->log(), $this->translator(Calendar::class));
	}

	public function persistence(): Persistence {
		return new Persistence($this->thermostat);
	}

	public function display(): Display {
		return new Display($this->thermostat, $this->commandLookup(), $this->statusLabels());
	}

	public function engineRunner(): EngineRunner {
		return new EngineRunner($this->thermostat);
	}

	public function actionList(): ActionList {
		return new ActionList($this->thermostat, $this->commandLookup(), $this->log(), $this->translator(ActionList::class));
	}

	public function powerCalculator(): Calculator {
		return new Calculator($this->settings(), $this->memory(), $this->log(), $this->translator(Calculator::class));
	}

	public function coefficientLearner(): CoefficientLearner {
		return new CoefficientLearner($this->settings(), $this->memory(), $this->log(), $this->translator(CoefficientLearner::class), $this->clock());
	}

	public function scheduler(): Scheduler {
		return new Scheduler($this->thermostat, $this->commandLookup(), $this->log());
	}

	public function smartStart(): SmartStart {
		return new SmartStart($this->settings(), $this->memory(), $this->calendar(), $this->sensors(), $this->display(), new Controls($this->commandLookup()), $this->evaluator(), $this->powerCalculator(), $this->scheduler(), $this->log(), $this->translator(SmartStart::class), $this->clock());
	}

	public function actuator(): Actuator {
		return new Actuator($this->settings(), $this->memory(), $this->persistence(), $this->display(), $this->actionList(), $this->engineRunner(), $this->log(), $this->statusLabels(), $this->translator(Actuator::class));
	}

	public function windows(): Windows {
		return new Windows($this->settings(), $this->memory(), $this->display(), new WindowSensors(), $this->actuator(), $this->engineRunner(), $this->log(), $this->statusLabels(), $this->translator(Windows::class), $this->clock());
	}

	public function temporalEngine(): TemporalEngine {
		return new TemporalEngine($this->settings(), $this->memory(), $this->persistence(), $this->evaluator(), $this->display(), $this->sensors(), $this->actuator(), $this->scheduler(), $this->powerCalculator(), $this->smartStart(), $this->coefficientLearner(), new Planner(), $this->log(), $this->statusLabels(), $this->translator(TemporalEngine::class), $this->clock());
	}

	public function hysteresisEngine(): HysteresisEngine {
		return new HysteresisEngine($this->settings(), $this->memory(), $this->display(), $this->sensors(), $this->actuator(), new HysteresisDecision($this->settings(), $this->log(), $this->statusLabels(), $this->translator(HysteresisDecision::class)), $this->log(), $this->statusLabels(), $this->translator(HysteresisEngine::class), $this->clock());
	}

	public function sensorWatch(): SensorWatch {
		return new SensorWatch($this->settings(), $this->memory(), $this->display(), $this->sensors(), $this->actuator(), $this->log(), $this->translator(SensorWatch::class), $this->clock());
	}

	public function commandHandler(): Handler {
		return new Handler($this->settings(), $this->memory(), $this->persistence(), $this->display(), $this->actuator(), $this->engineRunner(), $this->statusLabels());
	}

	public function commands(): Commands {
		return new Commands($this->thermostat, $this->scheduler(), $this->translator(Commands::class));
	}

	public function configuration(): Configuration {
		return new Configuration($this->settings(), $this->translator(Configuration::class));
	}

	public function statistics(): Statistics {
		return new Statistics($this->settings(), $this->evaluator(), new History($this->thermostat), $this->clock());
	}
}
