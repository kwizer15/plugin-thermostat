<?php

require_once __DIR__ . '/../../Metier/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Jeedom\Plugin\Thermostat\Domain\Power\Calculator;

class PowerCalculatorTest extends TestCase {

	private $settings;
	private $memory;
	private $log;

	protected function setUp() {
		$this->settings = new InMemorySettings();
		$this->memory = new InMemoryMemory();
		$this->log = new RecordingLog();
	}

	private function compute($_consigne, $_tempIn, $_tempOut, $_allowOverfull = false) {
		return (new Calculator($this->settings, $this->memory, $this->log, new IdentityTranslator()))->compute($_consigne, $_tempIn, $_tempOut, $_allowOverfull);
	}

	public function testHeatingPowerCombinesIndoorAndOutdoorGaps() {
		$this->assertEquals(array('power' => 40, 'direction' => 1), $this->compute(20, 19, 5));
		$this->assertSame(0, $this->memory->values['temp_threshold']);
	}

	public function testCoolingPowerUsesCoolingCoefficientsAndOffset() {
		$this->settings = new InMemorySettings(array('coeff_indoor_cool' => 20, 'coeff_outdoor_cool' => 1, 'offset_cool' => 3));

		$this->assertEquals(array('power' => 33, 'direction' => -1), $this->compute(24, 25, 34));
	}

	public function testMissingOutdoorTemperatureFallsBackToSetpoint() {
		$this->assertEquals(array('power' => 10, 'direction' => 1), $this->compute(20, 19, ''));
		$this->assertContains('debug Attention température extérieure erronée : ', $this->log->lines);
	}

	public function testKeepsHeatingJustAboveSetpointWhenAlreadyHeating() {
		$this->settings = new InMemorySettings(array('offset_heat' => 10, 'direction::delta::heat' => 100));
		$this->assertSame(-1, $this->compute(20, 20.3, 10)['direction']);

		$this->memory = new InMemoryMemory(array('lastState' => 'heat'));
		$this->assertEquals(array('power' => 27, 'direction' => 1), $this->compute(20, 20.3, 10));
		$this->assertSame(-1, $this->compute(20, 20.5, 10)['direction']);
	}

	public function testOutdoorColderThanDeltaForcesHeating() {
		$this->settings = new InMemorySettings(array('direction::delta::heat' => 5));

		$this->assertSame(1, $this->compute(20, 20.2, 14)['direction']);
		$this->assertSame(-1, $this->compute(20, 20.2, 16)['direction']);
	}

	public function testKeepsCoolingJustBelowSetpointWhenAlreadyCooling() {
		$this->settings = new InMemorySettings(array('direction::delta::heat' => 100, 'direction::delta::cool' => -100));
		$this->assertSame(1, $this->compute(24, 23.7, 30)['direction']);

		$this->memory = new InMemoryMemory(array('lastState' => 'cool'));
		$this->assertSame(-1, $this->compute(24, 23.7, 30)['direction']);
		$this->assertSame(1, $this->compute(24, 23.5, 30)['direction']);
	}

	public function testFarAboveSetpointDoesNothingAndReportsOnce() {
		$this->settings = new InMemorySettings(array('direction::delta::heat' => -100));

		$this->assertEquals(array('power' => 0, 'direction' => 1), $this->compute(20, 21.5, 5));
		$this->assertSame(1, $this->memory->values['temp_threshold']);
		$this->compute(20, 21.5, 5);
		$this->assertCount(1, preg_grep('/supérieure à la consigne de plus de 1.5/', $this->log->lines));
	}

	public function testFarBelowSetpointInCoolingDirectionDoesNothing() {
		$this->settings = new InMemorySettings(array('direction::delta::cool' => 100));

		$this->assertEquals(array('power' => 0, 'direction' => -1), $this->compute(24, 22.5, 30));
		$this->assertSame(1, $this->memory->values['temp_threshold']);
	}

	public function testPowerIsCappedAtHundredUnlessOverfullAllowed() {
		$this->assertEquals(100, $this->compute(22, 15, 0)['power']);
		$this->assertEquals(114, $this->compute(22, 15, 0, true)['power']);
	}

	public function testSlightlyNegativePowerIsZero() {
		$this->settings = new InMemorySettings(array('offset_heat' => -3));

		$this->assertEquals(0, $this->compute(20, 19.9, 20)['power']);
	}

	public function testNegativePowerIsZero() {
		$this->settings = new InMemorySettings(array('offset_heat' => -50));

		$this->assertEquals(0, $this->compute(20, 19.5, 18)['power']);
	}

	public function testReducesPowerAfterFullCycle() {
		$this->settings = new InMemorySettings(array('offset_nextFullCyle' => 15, 'threshold_heathot' => 80));
		$this->memory = new InMemoryMemory(array('last_power' => 100));

		$this->assertEquals(25, $this->compute(20, 19, 5)['power']);
		$this->assertEquals(40, $this->compute(20, 19, 5, true)['power']);
	}

	public function testReducesPowerAfterNearlyFullCycle() {
		$this->settings = new InMemorySettings(array('offset_nextFullCyle' => 15, 'threshold_heathot' => 80));
		$this->memory = new InMemoryMemory(array('last_power' => 90));

		$this->assertEquals(35, $this->compute(20, 19, 5)['power']);
	}

	public function testNoReductionBelowHeatHotThreshold() {
		$this->settings = new InMemorySettings(array('offset_nextFullCyle' => 15, 'threshold_heathot' => 95));
		$this->memory = new InMemoryMemory(array('last_power' => 90));

		$this->assertEquals(40, $this->compute(20, 19, 5)['power']);
	}
}
