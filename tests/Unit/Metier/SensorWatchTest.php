<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Metier;

require_once __DIR__ . '/../../Metier/bootstrap.php';

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\SensorWatch\SensorWatch;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingPersistence;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingRunner;
use Jeedom\Plugin\Thermostat\Tests\Metier\FixedSensors;
use Jeedom\Plugin\Thermostat\Tests\Metier\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingActions;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingLog;
use PHPUnit\Framework\TestCase;

class SensorWatchTest extends TestCase {

	private $settings;
	private $memory;
	private $display;
	private $sensors;
	private $actions;
	private $log;

	protected function setUp() {
		setMetierNow('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings(array('maxTimeUpdateTemp' => '', 'failure' => array(array('cmd' => '#alarm#')), 'temperature_indoor_min' => 12, 'temperature_indoor_max' => 26));
		$this->memory = new InMemoryMemory();
		$this->display = new InMemoryDisplay();
		$this->sensors = new FixedSensors(19, 5);
		$this->sensors->collectDate = '2026-01-15 09:59:00';
		$this->actions = new RecordingActions();
		$this->log = new RecordingLog();
	}

	private function check() {
		$actuator = new Actuator($this->settings, $this->memory, new CountingPersistence(), $this->display, $this->actions, new CountingRunner(), $this->log, new StatusLabels(new IdentityTranslator()), new IdentityTranslator());
		(new SensorWatch($this->settings, $this->memory, $this->display, $this->sensors, $actuator, $this->log, new IdentityTranslator()))->check();
	}

	public function testTemperatureInRangeClearsAlert() {
		$this->memory->values['temp_threshold'] = 1;

		$this->check();

		$this->assertSame(0, $this->memory->values['temp_threshold']);
		$this->assertSame(array(), $this->actions->executed);
	}

	/**
	 * @dataProvider outOfRange
	 */
	public function testOutOfRangeIsFailureOnce($_temperature, $_message) {
		$this->sensors->indoor = $_temperature;

		$this->check();
		$this->check();

		$this->assertSame(array('#alarm# (own included)'), $this->actions->executed);
		$this->assertSame(1, $this->memory->values['temp_threshold']);
		$this->assertCount(1, preg_grep('/^error ' . $_message . '/', $this->log->lines));
	}

	public function outOfRange() {
		return array(
			'below minimum' => array(11.9, 'Attention la température intérieure est en dessous'),
			'above maximum' => array(26.1, 'Attention la température intérieure est au dessus'),
		);
	}

	public function testBoundsAreInclusive() {
		$this->sensors->indoor = 12;
		$this->check();
		$this->assertSame(0, $this->memory->values['temp_threshold']);

		$this->sensors->indoor = 26;
		$this->check();
		$this->assertSame(0, $this->memory->values['temp_threshold']);
	}

	public function testMissingTemperatureOrNonNumericBoundsAreIgnored() {
		$this->sensors->indoor = '';
		$this->check();
		$this->assertSame(0, $this->memory->values['temp_threshold']);

		$this->settings->values['temperature_indoor_min'] = 'abc';
		$this->settings->values['temperature_indoor_max'] = '';
		$this->sensors->indoor = -50;
		$this->check();
		$this->assertSame(0, $this->memory->values['temp_threshold']);
	}

	public function testStaleSensorIsFailure() {
		$this->settings->values['maxTimeUpdateTemp'] = 60;
		$this->sensors->collectDate = '2026-01-15 08:59:59';

		$this->check();

		$this->assertSame(1, $this->memory->values['temp_threshold']);
		$this->assertCount(1, preg_grep('/^error Attention il n\'y a pas eu de mise à jour/', $this->log->lines));

		$this->sensors->collectDate = '2026-01-15 09:00:00';
		$this->check();
		$this->assertSame(0, $this->memory->values['temp_threshold']);
	}

	public function testModeOffSkipsChecks() {
		$this->display->mode = 'Off';
		$this->sensors->indoor = 5;
		$this->memory->values['temp_threshold'] = 1;

		$this->check();

		$this->assertSame(1, $this->memory->values['temp_threshold']);
		$this->assertSame(array(), $this->actions->executed);
	}
}
