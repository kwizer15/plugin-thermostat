<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Domain;

use Jeedom\Plugin\Thermostat\Domain\Power\Calculator;
use Jeedom\Plugin\Thermostat\Domain\SmartStart\SmartStart;
use Jeedom\Plugin\Thermostat\Tests\Double\FixedClock;
use Jeedom\Plugin\Thermostat\Tests\Double\FixedSensors;
use Jeedom\Plugin\Thermostat\Tests\Double\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Double\NumericEvaluator;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingControls;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingLog;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingScheduling;
use Jeedom\Plugin\Thermostat\Tests\Double\ScriptedCalendar;
use PHPUnit\Framework\TestCase;

class SmartStartTest extends TestCase {

	private $settings;
	private $memory;
	private $calendar;
	private $sensors;
	private $scheduling;
	private $display;
	private $controls;
	private $log;
	private $clock;

	protected function setUp() {
		$this->clock = new FixedClock('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings();
		$this->memory = new InMemoryMemory();
		$this->calendar = new ScriptedCalendar();
		$this->sensors = new FixedSensors(19, 5);
		$this->scheduling = new RecordingScheduling();
		$this->display = new InMemoryDisplay();
		$this->controls = new RecordingControls();
		$this->log = new RecordingLog();
	}

	private function smartStart() {
		return new SmartStart($this->settings, $this->memory, $this->calendar, $this->sensors, $this->display, $this->controls, new NumericEvaluator(), new Calculator($this->settings, $this->memory, $this->log, new IdentityTranslator()), $this->scheduling, $this->log, new IdentityTranslator(), $this->clock);
	}

	private function event($_date, $_consigne = '21') {
		return array('date' => $_date, 'consigne' => $_consigne, 'type' => 'thermostat');
	}

	public function testSchedulesStartBeforeEventFromHeatingDuration() {
		$this->calendar->next = $this->event('2026-01-15 18:00:00');

		$this->smartStart()->plan();

		$next = $this->event('2026-01-15 18:00:00');
		$next['schedule'] = '2026-01-15 17:29:00';
		$this->assertSame(array(array('2026-01-15 17:29:00', false, $next)), $this->scheduling->calls);
	}

	public function testAnticipationFactorStretchesDuration() {
		$this->settings->values['smart_start_factor'] = 0.5;
		$this->calendar->next = $this->event('2026-01-15 18:00:00');

		$this->smartStart()->plan();

		$this->assertSame('2026-01-15 17:44:00', $this->scheduling->calls[0][0]);
	}

	public function testPowerAboveHundredLengthensAnticipation() {
		$this->sensors = new FixedSensors(15, -5);
		$this->calendar->next = $this->event('2026-01-15 18:00:00');

		$this->smartStart()->plan();

		$this->assertSame('2026-01-15 16:53:00', $this->scheduling->calls[0][0]);
	}

	public function testUsesEvaluatedCycle() {
		$this->settings->values['cycle'] = '30';
		$this->calendar->next = $this->event('2026-01-15 18:00:00');

		$this->smartStart()->plan();

		$this->assertSame('2026-01-15 17:44:00', $this->scheduling->calls[0][0]);
	}

	/**
	 * @dataProvider noSchedule
	 */
	public function testDoesNotSchedule($_engine, $_available, $_next) {
		$this->settings->values['engine'] = $_engine;
		$this->calendar->available = $_available;
		$this->calendar->next = $_next;

		$this->smartStart()->plan();

		$this->assertSame(array(), $this->scheduling->calls);
	}

	public function noSchedule() {
		return array(
			'hysteresis engine' => array('hysteresis', true, $this->event('2026-01-15 18:00:00')),
			'no calendar' => array('temporal', false, $this->event('2026-01-15 18:00:00')),
			'no event' => array('temporal', true, null),
			'past event' => array('temporal', true, $this->event('2026-01-15 09:00:00')),
			'start too soon' => array('temporal', true, $this->event('2026-01-15 10:33:00')),
		);
	}

	public function testStartJustAfterTwoMinutesIsScheduled() {
		$this->calendar->next = $this->event('2026-01-15 10:34:00');

		$this->smartStart()->plan();

		$this->assertSame('2026-01-15 10:03:00', $this->scheduling->calls[0][0]);
	}

	public function testShortHeatingIsNotScheduled() {
		$this->sensors = new FixedSensors(19, 19);
		$this->calendar->next = $this->event('2026-01-15 18:00:00', '19.7');
		$this->smartStart()->plan();
		$this->assertSame('2026-01-15 17:55:00', $this->scheduling->calls[0][0]);

		$this->scheduling->calls = array();
		$this->calendar->next = $this->event('2026-01-15 18:00:00', '19.6');
		$this->smartStart()->plan();
		$this->assertSame(array(), $this->scheduling->calls);
	}

	public function testRemembersStart() {
		$this->smartStart()->remember($this->event('2026-01-15 18:00:00', '21'));

		$this->assertSame(array('start' => '2026-01-15 10:00:00', 'date' => '2026-01-15 18:00:00', 'consigne' => 21, 'temperature' => 19), $this->memory->values['smartStart']);
	}

	private function pending($_start, $_consigne, $_date = '2026-01-15 10:00:00') {
		$this->memory->values['smartStart'] = array('start' => '2026-01-15 09:15:00', 'date' => $_date, 'consigne' => $_consigne, 'temperature' => $_start);
	}

	public function testLearnsFromLatePreheat() {
		$this->pending(18, 20);

		$this->smartStart()->learn(19);

		$this->assertSame(2.0, $this->settings->values['smart_start_factor']);
		$this->assertSame(1, $this->settings->values['smart_start_autolearn']);
		$this->assertSame(array('smart_start_factor' => 2.0), $this->settings->published);
		$this->assertNull($this->memory->values['smartStart']);
	}

	public function testSmoothsBoundsAndCaps() {
		$this->settings->values['smart_start_autolearn'] = 10;
		$this->pending(18, 20);

		$this->smartStart()->learn(19);

		$this->assertSame(round(12 / 11, 2), $this->settings->values['smart_start_factor']);
		$this->assertSame(10, $this->settings->values['smart_start_autolearn']);
	}

	public function testRatioIsBoundedPerEvent() {
		$this->pending(18, 21);
		$this->smartStart()->learn(18.2);
		$this->assertSame(2.0, $this->settings->values['smart_start_factor']);

		$this->settings->values['smart_start_factor'] = 2;
		$this->settings->values['smart_start_autolearn'] = 0;
		$this->pending(19.5, 20);
		$this->smartStart()->learn(21.5);
		$this->assertSame(1.0, $this->settings->values['smart_start_factor']);
	}

	public function testFactorIsBounded() {
		$this->settings->values['smart_start_factor'] = 2.5;
		$this->pending(18, 20);
		$this->smartStart()->learn(19);
		$this->assertSame(3.0, $this->settings->values['smart_start_factor']);

		$this->settings->values['smart_start_factor'] = 0.6;
		$this->settings->values['smart_start_autolearn'] = 0;
		$this->pending(18, 20);
		$this->smartStart()->learn(22);
		$this->assertSame(0.5, $this->settings->values['smart_start_factor']);
	}

	public function testWaitsForEventTime() {
		$this->pending(18, 20, '2026-01-15 10:01:01');

		$this->smartStart()->learn(19);

		$this->assertSame(array(), $this->settings->published);
		$this->assertInternalType('array', $this->memory->values['smartStart']);
	}

	/**
	 * @dataProvider forgotten
	 */
	public function testForgetsWithoutLearning($_start, $_consigne, $_temperature, $_date) {
		$this->pending($_start, $_consigne, $_date);

		$this->smartStart()->learn($_temperature);

		$this->assertSame(array(), $this->settings->published);
		$this->assertNull($this->memory->values['smartStart']);
	}

	public function forgotten() {
		return array(
			'event long past' => array(18, 20, 19, '2026-01-15 07:59:59'),
			'little to heat' => array(19.6, 20, 21, '2026-01-15 10:00:00'),
			'no rise' => array(18, 20, 18, '2026-01-15 10:00:00'),
		);
	}

	private function options(array $_next) {
		return array('thermostat_id' => 1, 'smartThermostat' => 1, 'next' => $_next);
	}

	public function testTriggerSendsSetpointAndRemembersStart() {
		$this->settings->values['smart_start'] = 1;

		$this->smartStart()->trigger($this->options($this->event('2026-01-15 11:00:00', '21')));

		$this->assertSame(array('setpoint 21'), $this->controls->calls);
		$this->assertSame('2026-01-15 11:00:00', $this->memory->values['smartStart']['date']);
	}

	public function testTriggerRunsExistingMode() {
		$this->settings->values['smart_start'] = 1;
		$this->controls->modes = array(12);
		$next = array('date' => '2026-01-15 11:00:00', 'consigne' => '21', 'type' => 'mode', 'cmd' => 12);

		$this->smartStart()->trigger($this->options($next));
		$next['cmd'] = 13;
		$this->smartStart()->trigger($this->options($next));

		$this->assertSame(array('mode 12'), $this->controls->calls);
	}

	public function testTriggerDoesNothingWhenDisabledLockedOrCalendarInactive() {
		$next = $this->event('2026-01-15 11:00:00', '21');
		$next['calendar_id'] = 4;
		$this->smartStart()->trigger($this->options($next));

		$this->settings->values['smart_start'] = 1;
		$this->display->locked = true;
		$this->smartStart()->trigger($this->options($next));

		$this->display->locked = false;
		$this->calendar->inactive = array(4);
		$this->smartStart()->trigger($this->options($next));

		$this->assertSame(array(), $this->controls->calls);
		$this->assertSame('', $this->memory->values['smartStart']);

		$this->calendar->inactive = array(5);
		$this->smartStart()->trigger($this->options($next));
		$this->assertSame(array('setpoint 21'), $this->controls->calls);
	}
}
