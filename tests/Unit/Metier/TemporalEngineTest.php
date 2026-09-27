<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Metier;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Cycle\Planner;
use Jeedom\Plugin\Thermostat\Domain\Engine\TemporalEngine;
use Jeedom\Plugin\Thermostat\Domain\Learning\CoefficientLearner;
use Jeedom\Plugin\Thermostat\Domain\Power\Calculator;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\SmartStart;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingPersistence;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingRunner;
use Jeedom\Plugin\Thermostat\Tests\Metier\FixedClock;
use Jeedom\Plugin\Thermostat\Tests\Metier\FixedSensors;
use Jeedom\Plugin\Thermostat\Tests\Metier\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Metier\NumericEvaluator;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingActions;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingControls;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingLog;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingScheduling;
use Jeedom\Plugin\Thermostat\Tests\Metier\ScriptedCalendar;
use PHPUnit\Framework\TestCase;

class TemporalEngineTest extends TestCase {

	private $settings;
	private $memory;
	private $persistence;
	private $display;
	private $sensors;
	private $actions;
	private $scheduling;
	private $calendar;
	private $log;
	private $clock;

	protected function setUp() {
		$this->clock = new FixedClock('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings(array('cycle' => 60, 'autolearn' => 0, 'failureActuator' => array(array('cmd' => '#relay#')), 'failure' => array(array('cmd' => '#alarm#'))));
		$this->memory = new InMemoryMemory();
		$this->persistence = new CountingPersistence();
		$this->display = new InMemoryDisplay();
		$this->sensors = new FixedSensors(19, 5);
		$this->sensors->collectDate = '2026-01-15 09:59:00';
		$this->actions = new RecordingActions();
		$this->scheduling = new RecordingScheduling();
		$this->calendar = new ScriptedCalendar();
		$this->log = new RecordingLog();
	}

	private function run_() {
		$evaluator = new NumericEvaluator();
		$power = new Calculator($this->settings, $this->memory, $this->log, new IdentityTranslator());
		$actuator = new Actuator($this->settings, $this->memory, $this->persistence, $this->display, $this->actions, new CountingRunner(), $this->log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator());
		$smartStart = new SmartStart($this->settings, $this->memory, $this->calendar, $this->sensors, $this->display, new RecordingControls(), $evaluator, $power, $this->scheduling, $this->log, new IdentityTranslator(), $this->clock);
		$learner = new CoefficientLearner($this->settings, $this->memory, $this->log, new IdentityTranslator(), $this->clock);
		(new TemporalEngine($this->settings, $this->memory, $this->persistence, $evaluator, $this->display, $this->sensors, $actuator, $this->scheduling, $power, $smartStart, $learner, new Planner(), $this->log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator(), $this->clock))->run();
	}

	public function testHeatsForPowerShareOfCycleAndSchedulesStop() {
		$this->run_();

		$this->assertSame(array(
			array('2026-01-15 11:00:00', false, false),
			array('2026-01-15 10:24:00', true, false),
		), $this->scheduling->calls);
		$this->assertSame(array('#heater#'), $this->actions->executed);
		$this->assertSame(40.0, $this->display->power);
		$this->assertSame('heat', $this->memory->values['lastState']);
		$this->assertSame(40.0, $this->memory->values['last_power']);
		$this->assertSame(20, $this->memory->values['lastOrder']);
		$this->assertSame(19, $this->memory->values['lastTempIn']);
		$this->assertSame(5, $this->memory->values['lastTempOut']);
		$this->assertSame('2026-01-15 10:54:00', $this->settings->values['endDate']);
		$this->assertSame(1, $this->persistence->persists);
	}

	public function testSuspendedOnlyReschedules() {
		$this->display->status = 'Suspendu';

		$this->run_();

		$this->assertCount(1, $this->scheduling->calls);
		$this->assertSame(array(), $this->actions->executed);
	}

	public function testSmartStartIsPlannedWhenEnabled() {
		$this->settings->values['smart_start'] = 1;
		$this->calendar->next = array('date' => '2026-01-15 18:00:00', 'consigne' => '21', 'type' => 'thermostat');

		$this->run_();

		$this->assertSame('2026-01-15 17:29:00', $this->scheduling->calls[1][0]);
	}

	public function testModeOffStops() {
		$this->display->mode = 'Off';
		$this->display->status = 'Chauffage';

		$this->run_();

		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame(0, $this->memory->values['lastOrder']);
	}

	public function testStaleSensorTriggersFailureOnce() {
		$this->sensors->collectDate = '2026-01-15 08:59:59';

		$this->run_();
		$this->run_();

		$this->assertSame(array('#alarm# (own included)'), $this->actions->executed);
		$this->assertSame('Défaillance sonde', $this->display->status);
	}

	public function testNonNumericTemperatureIsFailure() {
		$this->sensors->indoor = 'abc';

		$this->run_();

		$this->assertSame('Défaillance sonde', $this->display->status);
		$this->assertSame(1, $this->memory->values['temp_threshold']);
		$this->assertSame(array(), $this->actions->executed);
	}

	public function testDetectsActuatorFailureAfterTwoCycles() {
		$this->settings->values['coeff_indoor_heat_autolearn'] = 26;
		$this->memory->values['lastState'] = 'heat';
		$this->memory->values['lastOrder'] = 20.5;
		$this->memory->values['lastTempIn'] = 19.5;

		$this->run_();
		$this->assertSame(1, $this->memory->values['nbConsecutiveFaillure']);
		$this->assertNotContains('#relay# (own included)', $this->actions->executed);

		$this->memory->values['lastTempIn'] = 19.5;
		$this->memory->values['lastOrder'] = 20.5;
		$this->run_();
		$this->assertSame(2, $this->memory->values['nbConsecutiveFaillure']);
		$this->assertContains('#relay# (own included)', $this->actions->executed);
	}

	public function testActuatorFailureNeedsLearnedCoefficient() {
		$this->settings->values['coeff_indoor_heat_autolearn'] = 25;
		$this->memory->values['lastState'] = 'heat';
		$this->memory->values['lastOrder'] = 20.5;
		$this->memory->values['lastTempIn'] = 19.5;
		$this->memory->values['nbConsecutiveFaillure'] = 1;

		$this->run_();

		$this->assertSame(0, $this->memory->values['nbConsecutiveFaillure']);
	}

	public function testDetectsCoolingFailure() {
		$this->settings->values['coeff_indoor_cool_autolearn'] = 26;
		$this->memory->values['lastState'] = 'cool';
		$this->memory->values['lastOrder'] = 17.5;
		$this->memory->values['lastTempIn'] = 18.5;

		$this->run_();

		$this->assertSame(1, $this->memory->values['nbConsecutiveFaillure']);
	}

	public function testDeltaOrderRetriesAboveSetpoint() {
		$this->memory->values['deltaOrder'] = 1;

		$this->run_();

		$this->assertSame(46.0, $this->display->power);
	}

	public function testTooShortCycleStops() {
		$this->sensors->indoor = 20;
		$this->sensors->outdoor = 20;
		$this->display->status = 'Chauffage';

		$this->run_();

		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame('stop', $this->memory->values['lastState']);
		$this->assertCount(1, $this->scheduling->calls);
		$this->assertSame(2, $this->persistence->persists);
	}

	public function testFullCycleCancelsPendingStop() {
		$this->sensors->indoor = 15;
		$this->sensors->outdoor = -5;

		$this->run_();

		$this->assertSame(array(null, true, false), $this->scheduling->calls[1]);
		$this->assertSame(100.0, $this->display->power);
	}

	public function testCoolsWhenAboveSetpointInWarmWeather() {
		$this->sensors->indoor = 21;
		$this->sensors->outdoor = 30;

		$this->run_();

		$this->assertSame(array('#cooler#'), $this->actions->executed);
		$this->assertSame('cool', $this->memory->values['lastState']);
	}

	public function testDirectionChangeStopsAndRunsAgainOneMinuteLater() {
		$this->sensors->indoor = 21;
		$this->sensors->outdoor = 30;
		$this->memory->values['lastState'] = 'heat';
		$this->display->status = 'Chauffage';

		$this->run_();

		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame('stop', $this->memory->values['lastState']);
		$this->assertSame(array('2026-01-15 10:01:00', false, false), end($this->scheduling->calls));
	}
}
