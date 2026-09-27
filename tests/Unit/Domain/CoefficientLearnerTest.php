<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Domain;

use Jeedom\Plugin\Thermostat\Domain\Learning\CoefficientLearner;
use Jeedom\Plugin\Thermostat\Tests\Double\FixedClock;
use Jeedom\Plugin\Thermostat\Tests\Double\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Double\RecordingLog;
use PHPUnit\Framework\TestCase;

class CoefficientLearnerTest extends TestCase {

	private $settings;
	private $memory;
	private $log;
	private $clock;

	protected function setUp() {
		$this->clock = new FixedClock('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings();
		$this->memory = new InMemoryMemory(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 19));
		$this->log = new RecordingLog();
	}

	private function learn($_tempIn, $_tempOut) {
		(new CoefficientLearner($this->settings, $this->memory, $this->log, new IdentityTranslator(), $this->clock))->learn($_tempIn, $_tempOut);
	}

	public function testLearnsIndoorHeatFromTemperatureRise() {
		$this->learn(19.5, 5);

		$this->assertSame(15.0, $this->settings->values['coeff_indoor_heat']);
		$this->assertSame(2, $this->settings->values['coeff_indoor_heat_autolearn']);
		$this->assertSame(array('coeff_indoor_heat' => 15.0), $this->settings->published);
	}

	public function testIndoorLearningNeedsSetpointAbovePreviousTemperature() {
		$this->memory->values['lastOrder'] = 18.5;
		$this->memory->values['lastTempIn'] = 19;

		$this->learn(19.5, 5);

		$this->assertSame(array('coeff_outdoor_heat'), array_keys($this->settings->published));
	}

	public function testLearnsOutdoorHeatWhenTemperatureDidNotRise() {
		$this->learn(18.5, 5);

		$this->assertSame(3.0, $this->settings->values['coeff_outdoor_heat']);
		$this->assertSame(1, $this->settings->values['coeff_outdoor_heat_autolearn']);
	}

	public function testNoOutdoorHeatLearningWhenOutsideWarmerThanSetpoint() {
		$this->learn(18.5, 25);

		$this->assertSame(array(), $this->settings->published);
	}

	public function testLearnsIndoorCoolFromTemperatureDrop() {
		$this->memory = new InMemoryMemory(array('lastState' => 'cool', 'last_power' => 50, 'lastOrder' => 24, 'lastTempIn' => 26));

		$this->learn(25, 30);

		$this->assertSame(15.0, $this->settings->values['coeff_indoor_cool']);
	}

	public function testLearnsOutdoorCoolWhenTemperatureDidNotDrop() {
		$this->memory = new InMemoryMemory(array('lastState' => 'cool', 'last_power' => 50, 'lastOrder' => 24, 'lastTempIn' => 25));

		$this->learn(25.5, 30);

		$this->assertSame(4.5, $this->settings->values['coeff_outdoor_cool']);
	}

	public function testNegativeCoefficientBecomesZero() {
		$this->settings->values['coeff_indoor_heat'] = 100;
		$this->memory->values['lastTempIn'] = 22;

		$this->learn(21, 5);

		$this->assertSame(0.0, $this->settings->values['coeff_outdoor_heat']);
		$this->assertSame(1, $this->settings->values['coeff_outdoor_heat_autolearn']);
	}

	public function testCountIsCappedAtFifty() {
		$this->settings->values['coeff_indoor_heat_autolearn'] = 50;

		$this->learn(19.5, 5);

		$this->assertSame(50, $this->settings->values['coeff_indoor_heat_autolearn']);
		$this->assertSame(round((10 * 50 + 20) / 51, 2), $this->settings->values['coeff_indoor_heat']);
	}

	/**
	 * @dataProvider notLearning
	 */
	public function testDoesNotLearn(array $_settings, array $_memory) {
		$this->settings = new InMemorySettings($_settings);
		$this->memory = new InMemoryMemory(array_merge(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 19), $_memory));

		$this->learn(19.5, 5);

		$this->assertSame(array(), $this->settings->published);
	}

	public function notLearning() {
		return array(
			'autolearn off' => array(array('autolearn' => 0), array()),
			'cycle not ended' => array(array('endDate' => '2099-01-01 00:00:00'), array()),
			'cycle ending now' => array(array('endDate' => '2026-01-15 10:00:00'), array()),
			'three failures' => array(array(), array('nbConsecutiveFaillure' => 3)),
			'full power' => array(array(), array('last_power' => 100)),
			'no power' => array(array(), array('last_power' => 0)),
			'stopped' => array(array(), array('lastState' => 'stop')),
		);
	}

	public function testLearnsOnceCycleHasEnded() {
		$this->settings->values['endDate'] = '2026-01-15 09:59:59';

		$this->learn(19.5, 5);

		$this->assertArrayHasKey('coeff_indoor_heat', $this->settings->published);
	}

	public function testTwoFailuresStillLearn() {
		$this->memory->values['nbConsecutiveFaillure'] = 2;

		$this->learn(19.5, 5);

		$this->assertArrayHasKey('coeff_indoor_heat', $this->settings->published);
	}
}
