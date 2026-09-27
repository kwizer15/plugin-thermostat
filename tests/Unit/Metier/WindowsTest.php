<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Metier;

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Window\Windows;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingPersistence;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingRunner;
use Jeedom\Plugin\Thermostat\Tests\Metier\FixedClock;
use Jeedom\Plugin\Thermostat\Tests\Metier\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryWindowSensors;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingActions;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingLog;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingWindowTimer;
use PHPUnit\Framework\TestCase;

class WindowsTest extends TestCase {

	private $settings;
	private $memory;
	private $display;
	private $sensors;
	private $actions;
	private $engine;
	private $clock;
	private $timer;

	protected function setUp() {
		$this->clock = new FixedClock('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings();
		$this->memory = new InMemoryMemory();
		$this->display = new InMemoryDisplay();
		$this->display->status = 'Chauffage';
		$this->sensors = new InMemoryWindowSensors();
		$this->actions = new RecordingActions();
		$this->engine = new CountingRunner();
		$this->timer = new RecordingWindowTimer();
	}

	private function windows() {
		$actuator = new Actuator($this->settings, $this->memory, new CountingPersistence(), $this->display, $this->actions, $this->engine, new RecordingLog(), new StatusLabels(new IdentityTranslator()), new IdentityTranslator());
		return new Windows($this->settings, $this->memory, $this->display, $this->sensors, $actuator, $this->engine, new RecordingLog(), new StatusLabels(new IdentityTranslator()), new IdentityTranslator(), $this->clock, $this->timer);
	}

	private function configure(array $_windows) {
		$this->settings->values['window'] = $_windows;
	}

	private function notify($_cmdId, $_value) {
		$this->sensors->set($_cmdId, $_value);
		$this->windows()->handle(array('event_id' => $_cmdId, 'value' => $_value));
	}

	public function testOpeningSuspendsThermostat() {
		$this->configure(array(array('cmd' => '#7#')));

		$this->notify(7, 1);

		$this->assertSame('Suspendu', $this->display->status);
		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame(strtotime('2026-01-15 10:00:00'), $this->memory->values['window::state::open']);
		$this->assertSame(1, $this->memory->windowState(7));
	}

	public function testOnlyConfiguredWindowIsHandled() {
		$this->configure(array(array('cmd' => '#7#')));

		$this->notify(8, 1);

		$this->assertSame('Chauffage', $this->display->status);
		$this->assertSame(0, $this->memory->windowState(8));
		$this->assertSame(0, $this->memory->windowState(7));
	}

	public function testInvertedWindow() {
		$this->configure(array(array('cmd' => '#7#', 'invert' => 1)));

		$this->notify(7, 0);

		$this->assertSame('Suspendu', $this->display->status);
	}

	public function testOpeningIgnoredWhenOffOrAlreadySuspended() {
		$this->configure(array(array('cmd' => '#7#')));
		$this->display->mode = 'Off';
		$this->notify(7, 1);
		$this->assertSame('Chauffage', $this->display->status);
		$this->assertSame(1, $this->memory->windowState(7));

		$this->display->mode = 'Aucun';
		$this->display->status = 'Suspendu';
		$this->notify(7, 1);
		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(-1, $this->memory->values['window::state::open']);
	}

	public function testOpeningIgnoredWhenWindowClosedAgainOrUnknown() {
		$this->configure(array(array('cmd' => '#7#')));
		$this->sensors->set(7, 0);
		$this->windows()->open(array('cmd' => '#7#'));
		$this->assertSame('Chauffage', $this->display->status);

		$this->windows()->open(array('cmd' => '#9#'));
		$this->assertSame('Chauffage', $this->display->status);
	}

	public function testOpeningIgnoredWhenClosedDuringPause() {
		$this->sensors->set(7, 1, '2026-01-15 10:00:06');

		$this->windows()->open(array('cmd' => '#7#'));

		$this->assertSame('Chauffage', $this->display->status);
	}

	public function testOpeningWithPauseSchedulesCheckInsteadOfWaiting() {
		$this->configure(array(array('cmd' => '#7#', 'stopTime' => 1)));

		$this->notify(7, 1);

		$this->assertSame('Chauffage', $this->display->status);
		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(array(array('7', 'open', strtotime('2026-01-15 10:01:00'))), $this->timer->calls);
		$this->assertSame('2026-01-15 10:00:00', $this->memory->openedAt(7));
	}

	public function testPauseCheckSuspendsWhenWindowStillOpen() {
		$this->configure(array(array('cmd' => '#7#', 'stopTime' => 2)));
		$this->sensors->set(7, 1, '2026-01-15 09:58:00');
		$this->memory->setOpenedAt(7, '2026-01-15 09:58:00');

		$this->windows()->timer(7, 'open');

		$this->assertSame('Suspendu', $this->display->status);
		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame(strtotime('2026-01-15 10:00:00'), $this->memory->values['window::state::open']);
	}

	/**
	 * @dataProvider pauseCheckIgnored
	 */
	public function testPauseCheckDoesNothing(array $_windows, $_value) {
		$this->configure($_windows);
		if ($_value !== null) {
			$this->sensors->set(7, $_value, '2026-01-15 09:59:00');
		}
		$this->sensors->set(8, 1, '2026-01-15 09:58:00');
		$this->memory->setOpenedAt(7, '2026-01-15 09:58:00');

		$this->windows()->timer(7, 'open');

		$this->assertSame('Chauffage', $this->display->status);
		$this->assertSame(array(), $this->actions->executed);
	}

	public function pauseCheckIgnored() {
		return array(
			'closed meanwhile' => array(array(array('cmd' => '#7#', 'stopTime' => 2)), 0),
			'reopened after opening' => array(array(array('cmd' => '#7#', 'stopTime' => 2)), 1),
			'command deleted' => array(array(array('cmd' => '#7#', 'stopTime' => 2)), null),
			'window no longer configured' => array(array(array('cmd' => '#8#', 'stopTime' => 2)), 1),
		);
	}

	public function testClosingResumesThermostat() {
		$this->configure(array(array('cmd' => '#7#')));
		$this->notify(7, 1);

		$this->notify(7, 0);

		$this->assertSame('Calcul', $this->display->status);
		$this->assertSame(-1, $this->memory->values['window::state::open']);
		$this->assertSame('2026-01-15 10:00:00', $this->memory->closedAt(7));
		$this->assertSame(1, $this->engine->runs);
	}

	public function testClosingNeverSeenOpenIsIgnored() {
		$this->configure(array(array('cmd' => '#7#')));
		$this->display->status = 'Suspendu';

		$this->notify(7, 0);

		$this->assertSame(0, $this->engine->runs);
		$this->assertSame('', $this->memory->closedAt(7));
	}

	public function testClosingWhenNotSuspendedOnlyForgetsWindow() {
		$this->configure(array(array('cmd' => '#7#')));
		$this->memory->setWindowState(7, 1);

		$this->notify(7, 0);

		$this->assertSame(0, $this->memory->windowState(7));
		$this->assertSame(0, $this->engine->runs);
	}

	public function testClosingWaitsForOtherWindows() {
		$this->configure(array(array('cmd' => '#7#'), array('cmd' => '#8#')));
		$this->sensors->set(8, 1);
		$this->notify(7, 1);

		$this->notify(7, 0);

		$this->assertSame('Suspendu', $this->display->status);
		$this->assertSame(0, $this->engine->runs);
	}

	public function testClosingSkipsUnknownWindowCommands() {
		$this->configure(array(array('cmd' => '#6#'), array('cmd' => '#7#')));
		$this->notify(7, 1);
		unset($this->sensors->values[6]);

		$this->notify(7, 0);

		$this->assertSame(1, $this->engine->runs);
	}

	public function testClosingWaitsForRestartTimeOfOtherWindows() {
		$this->configure(array(array('cmd' => '#7#'), array('cmd' => '#8#', 'restartTime' => 5)));
		$this->sensors->set(8, 0);
		$this->memory->setClosedAt(8, '2026-01-15 09:56:00');
		$this->notify(7, 1);

		$this->notify(7, 0);
		$this->assertSame(0, $this->engine->runs);

		$this->memory->setClosedAt(8, '2026-01-15 09:55:01');
		$this->memory->setWindowState(7, 1);
		$this->notify(7, 0);
		$this->assertSame(1, $this->engine->runs);
	}

	public function testAlertsOnceWhenOpenTooLong() {
		$this->settings->values['window_alertIfOpenMoreThan'] = 30;
		$this->display->status = 'Suspendu';
		$this->memory->setOpenSince(strtotime('2026-01-15 09:29:59'));
		$log = new RecordingLog();
		$windows = new Windows($this->settings, $this->memory, $this->display, $this->sensors, new Actuator($this->settings, $this->memory, new CountingPersistence(), $this->display, $this->actions, $this->engine, $log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator()), $this->engine, $log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator(), $this->clock, $this->timer);

		$windows->alert();
		$windows->alert();

		$this->assertCount(1, preg_grep('/^error Attention le thermostat est suspendu/', $log->lines));
		$this->assertSame(1, $this->memory->alertSent());
	}

	public function testNoAlertBeforeDelayAndResetWhenResumed() {
		$this->settings->values['window_alertIfOpenMoreThan'] = 30;
		$this->display->status = 'Suspendu';
		$this->memory->setOpenSince(strtotime('2026-01-15 09:30:00'));
		$this->windows()->alert();
		$this->assertSame(0, $this->memory->alertSent());

		$this->memory->setAlertSent(1);
		$this->display->status = 'Chauffage';
		$this->memory->setOpenSince(strtotime('2026-01-15 09:00:00'));
		$this->windows()->alert();
		$this->assertSame(0, $this->memory->alertSent());
	}
}
