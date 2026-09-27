<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Domain;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Engine\HysteresisDecision;
use Jeedom\Plugin\Thermostat\Domain\Engine\HysteresisEngine;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Tests\Double\CountingPersistence;
use Jeedom\Plugin\Thermostat\Tests\Double\CountingRunner;
use Jeedom\Plugin\Thermostat\Tests\Double\FixedClock;
use Jeedom\Plugin\Thermostat\Tests\Double\FixedSensors;
use Jeedom\Plugin\Thermostat\Tests\Double\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingActions;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingLog;
use PHPUnit\Framework\TestCase;

class HysteresisEngineTest extends TestCase {

	private $settings;
	private $memory;
	private $display;
	private $sensors;
	private $actions;
	private $log;
	private $clock;

	protected function setUp() {
		$this->clock = new FixedClock('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings(array('failure' => array(array('cmd' => '#alarm#'))));
		$this->memory = new InMemoryMemory();
		$this->display = new InMemoryDisplay();
		$this->display->power = null;
		$this->sensors = new FixedSensors(19, 5);
		$this->sensors->collectDate = '2026-01-15 09:59:00';
		$this->actions = new RecordingActions();
		$this->log = new RecordingLog();
	}

	private function run_() {
		$actuator = new Actuator($this->settings, $this->memory, new CountingPersistence(), $this->display, $this->actions, new CountingRunner(), $this->log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator());
		(new HysteresisEngine($this->settings, $this->memory, $this->display, $this->sensors, $actuator, new HysteresisDecision($this->settings, $this->log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator()), $this->log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator(), $this->clock))->run();
	}

	public function testHeatsBelowBandAndHistorizesSetpoint() {
		$this->sensors->indoor = 18.5;
		$this->memory->values['temp_threshold'] = 1;

		$this->run_();

		$this->assertSame('Chauffage', $this->display->status);
		$this->assertSame(array('#heater#'), $this->actions->executed);
		$this->assertSame(array(20), $this->display->history);
		$this->assertSame(0, $this->memory->values['temp_threshold']);
	}

	public function testDoesNotRepeatCurrentAction() {
		$this->sensors->indoor = 18.5;
		$this->display->status = 'Chauffage';
		$this->run_();

		$this->sensors->indoor = 21.5;
		$this->display->status = 'Climatisation';
		$this->run_();

		$this->sensors->indoor = 20;
		$this->display->status = 'Arrêté';
		$this->memory->values['lastState'] = 'heat';
		$this->display->events = array();
		$this->run_();

		$this->assertSame(array(), $this->actions->executed);
	}

	public function testCoolsAboveBandAndStopsWhenHeatingOvershoots() {
		$this->sensors->indoor = 21.5;
		$this->run_();
		$this->assertSame('Climatisation', $this->display->status);

		$this->display->status = 'Chauffage';
		$this->memory->values['lastState'] = 'heat';
		$this->sensors->indoor = 22.5;
		$this->run_();
		$this->assertSame('Arrêté', $this->display->status);
	}

	public function testSuspendedDoesNothing() {
		$this->display->status = 'Suspendu';
		$this->sensors->indoor = 15;

		$this->run_();

		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(array(), $this->display->history);
	}

	public function testModeOffStopsUnlessAlreadyStopped() {
		$this->display->mode = 'Off';
		$this->display->status = 'Chauffage';
		$this->run_();
		$this->assertSame(array('#stopper#'), $this->actions->executed);

		$this->run_();
		$this->assertSame(array('#stopper#'), $this->actions->executed);
	}

	public function testStaleSensorTriggersFailureOnce() {
		$this->sensors->collectDate = '2026-01-15 08:59:59';

		$this->run_();
		$this->run_();

		$this->assertSame(array('#alarm# (own included)'), $this->actions->executed);
		$this->assertSame('Défaillance sonde', $this->display->status);
		$this->assertSame(1, $this->memory->values['temp_threshold']);
		$this->assertCount(1, preg_grep('/^error Attention il n\'y a pas eu de mise à jour/', $this->log->lines));
	}

	public function testFractionalUpdateDelayIsHonoured() {
		$this->settings->values['maxTimeUpdateTemp'] = 1.5;
		$this->sensors->indoor = 18.5;

		$this->run_();

		$this->assertSame(array('#heater#'), $this->actions->executed);
	}

	public function testStaleSensorMessage() {
		$this->sensors->collectDate = '2026-01-15 08:59:59';

		$this->run_();

		$this->assertContains('error Attention il n\'y a pas eu de mise à jour de la température depuis plus de : 60 minutes (2026-01-15 08:59:59)', $this->log->lines);
	}

	public function testStaleSensorWithoutFailureActionsStillReportsFailure() {
		$this->settings->values['failure'] = array();
		$this->sensors->collectDate = '2026-01-15 08:59:59';

		$this->run_();

		$this->assertSame('Défaillance sonde', $this->display->status);
	}

	public function testRecentOrUnknownCollectDateIsNotStale() {
		$this->sensors->collectDate = '2026-01-15 09:00:00';
		$this->run_();
		$this->sensors->collectDate = '';
		$this->run_();

		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(array(20, 20), $this->display->history);
	}
}
