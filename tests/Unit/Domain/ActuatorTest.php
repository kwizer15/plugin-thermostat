<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Domain;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Tests\Double\CountingPersistence;
use Jeedom\Plugin\Thermostat\Tests\Double\CountingRunner;
use Jeedom\Plugin\Thermostat\Tests\Double\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingActions;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingLog;
use PHPUnit\Framework\TestCase;

class ActuatorTest extends TestCase {

	private $settings;
	private $memory;
	private $persistence;
	private $display;
	private $actions;
	private $engine;

	protected function setUp() {
		$this->settings = new InMemorySettings();
		$this->memory = new InMemoryMemory();
		$this->persistence = new CountingPersistence();
		$this->display = new InMemoryDisplay();
		$this->actions = new RecordingActions();
		$this->engine = new CountingRunner();
	}

	private function actuator() {
		return new Actuator($this->settings, $this->memory, $this->persistence, $this->display, $this->actions, $this->engine, new RecordingLog(), new StatusLabels(new IdentityTranslator()), new IdentityTranslator());
	}

	public function testHeatExecutesActionsAndRecordsState() {
		$this->assertTrue($this->actuator()->heat());

		$this->assertSame(array('#heater#'), $this->actions->executed);
		$this->assertSame(array('status=Chauffage', 'active=1'), $this->display->events);
		$this->assertSame('heat', $this->memory->values['lastState']);
		$this->assertSame(1, $this->persistence->reloads);
	}

	public function testRepeatedHeatOnlyResendsActions() {
		$this->assertTrue($this->actuator()->heat(true));

		$this->assertSame(array('#heater#'), $this->actions->executed);
		$this->assertSame(array('status=Chauffage'), $this->display->events);
		$this->assertSame('', $this->memory->values['lastState']);
		$this->assertSame(0, $this->persistence->reloads);
	}

	/**
	 * @dataProvider blocked
	 */
	public function testHeatRefusedWhenOffOrSuspended($_mode, $_status) {
		$this->display->mode = $_mode;
		$this->display->status = $_status;

		$this->assertFalse($this->actuator()->heat());
		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(array(), $this->display->events);
	}

	public function blocked() {
		return array('off' => array('Off', 'Arrêté'), 'suspended' => array('Aucun', 'Suspendu'));
	}

	public function testHeatNotAllowedOrWithoutActionsStops() {
		$this->display->status = 'Chauffage';
		$this->settings->values['allow_mode'] = 'cool';
		$this->assertFalse($this->actuator()->heat());
		$this->assertSame(array('#stopper#'), $this->actions->executed);

		$this->display->status = 'Chauffage';
		$this->settings->values['allow_mode'] = 'all';
		$this->settings->values['heating'] = array();
		$this->assertFalse($this->actuator()->heat());
		$this->assertSame(array('#stopper#', '#stopper#'), $this->actions->executed);
	}

	public function testCoolMirrorsHeat() {
		$this->assertTrue($this->actuator()->cool());
		$this->assertSame(array('#cooler#'), $this->actions->executed);
		$this->assertSame('cool', $this->memory->values['lastState']);

		$this->settings->values['allow_mode'] = 'heat';
		$this->display->status = 'Climatisation';
		$this->assertFalse($this->actuator()->cool());

		$this->settings->values['allow_mode'] = 'all';
		$this->settings->values['cooling'] = array();
		$this->display->status = 'Climatisation';
		$this->assertFalse($this->actuator()->cool());
		$this->assertSame(array('#cooler#', '#stopper#', '#stopper#'), $this->actions->executed);
	}

	public function testStopExecutesActionsResetsOutputsAndPersists() {
		$this->display->status = 'Chauffage';
		$this->display->power = 40;

		$this->actuator()->stop();

		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame(array('power=0', 'active=0', 'status=Arrêté'), $this->display->events);
		$this->assertSame(1, $this->persistence->persists);
	}

	public function testStopWhenAlreadyStoppedDoesNothing() {
		$this->actuator()->stop();

		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(0, $this->persistence->persists);
	}

	public function testStopWhenStoppedWithPowerResendsWithoutPersisting() {
		$this->display->power = 12;

		$this->actuator()->stop();

		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame(0, $this->persistence->persists);
	}

	public function testStopForSuspensionKeepsStatus() {
		$this->display->status = 'Chauffage';

		$this->actuator()->stop(false, true);

		$this->assertSame('Chauffage', $this->display->status);
		$this->assertSame(1, $this->persistence->persists);
	}

	public function testOrderChangeFlagsModeChange() {
		$this->settings->values['orderChange'] = array(array('cmd' => '#notify#'));

		$this->actuator()->orderChange();

		$this->assertSame(array('#notify# {"modeChange":true}'), $this->actions->executed);
	}

	public function testOrderChangeAndFailuresBlockedWhenOffOrSuspended() {
		$this->settings->values['orderChange'] = array(array('cmd' => '#notify#'));
		$this->settings->values['failure'] = array(array('cmd' => '#alarm#'));
		$this->settings->values['failureActuator'] = array(array('cmd' => '#alarm#'));
		foreach (array(array('Off', 'Arrêté'), array('Aucun', 'Suspendu')) as $state) {
			$this->display->mode = $state[0];
			$this->display->status = $state[1];
			$this->actuator()->orderChange();
			$this->actuator()->failure();
			$this->actuator()->failureActuator();
		}

		$this->assertSame(array(), $this->actions->executed);
	}

	public function testFailuresIncludeOwnCommandsAndSetStatus() {
		$this->settings->values['failure'] = array(array('cmd' => '#alarm#'));
		$this->actuator()->failure();
		$this->assertSame('Défaillance sonde', $this->display->status);

		$this->settings->values['failureActuator'] = array(array('cmd' => '#relay#'));
		$this->actuator()->failureActuator();
		$this->assertSame('Défaillance chauffage', $this->display->status);

		$this->assertSame(array('#alarm# (own included)', '#relay# (own included)'), $this->actions->executed);
	}

	public function testFailuresWithoutActionsDoNothing() {
		$this->actuator()->failure();
		$this->actuator()->failureActuator();

		$this->assertSame(array(), $this->display->events);
	}

	public function testExecuteModeAppliesMatchingModesThenRunsEngine() {
		$this->settings->values['existingMode'] = array(
			array('name' => 'Eco', 'actions' => array(array('cmd' => '#a#'))),
			array('name' => 'Confort', 'actions' => array(array('cmd' => '#b#'))),
			array('name' => 'Eco', 'actions' => array(array('cmd' => '#c#'))),
		);
		$this->settings->values['orderChange'] = array(array('cmd' => '#notify#'));

		$this->actuator()->executeMode('Eco');

		$this->assertSame(array('mode #a# @20', 'mode #c# @20'), $this->actions->executed);
		$this->assertSame('Eco', $this->display->mode);
		$this->assertSame(1, $this->engine->runs);
	}

	public function testExecuteModeTriggersOrderChangeWhenSetpointChanged() {
		$this->settings->values['existingMode'] = array(array('name' => 'Eco', 'actions' => array(array('cmd' => '#a#'))));
		$this->settings->values['orderChange'] = array(array('cmd' => '#notify#'));
		$this->actions->modeSetsSetpoint = true;

		$this->actuator()->executeMode('Eco');

		$this->assertSame(array('mode #a# @20', '#notify# {"modeChange":true}'), $this->actions->executed);
	}

	public function testRepeatResendsCommandsOfCurrentStatus() {
		foreach (array('Chauffage' => '#heater#', 'Climatisation' => '#cooler#', 'Arrêté' => '#stopper#', 'Suspendu' => null) as $status => $expected) {
			$this->actions->executed = array();
			$this->display->status = $status;
			$this->actuator()->repeat();
			$this->assertSame($expected === null ? array() : array($expected), $this->actions->executed, $status);
		}
		$this->assertSame(0, $this->persistence->persists);
	}
}
