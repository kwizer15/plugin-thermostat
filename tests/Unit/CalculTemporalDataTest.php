<?php

class CalculTemporalDataTest extends ThermostatTestCase {

	private function thermostatAt($_indoor, $_outdoor, array $_configuration = array()) {
		$thermostat = $this->createThermostat($_configuration);
		$this->setValueOf($thermostat, 'temperature', $_indoor);
		$this->setValueOf($thermostat, 'temperature_outdoor', $_outdoor);
		return $thermostat;
	}

	public function testHeatingPower() {
		$thermostat = $this->thermostatAt(19, 5);

		$this->assertEquals(array('power' => 40, 'direction' => 1), $thermostat->calculTemporalData(20));
	}

	public function testCoolingPower() {
		$thermostat = $this->thermostatAt(25, 30);

		$this->assertEquals(array('power' => 46, 'direction' => -1), $thermostat->calculTemporalData(22));
	}

	public function testUsesCoefficientsAndOffsetOfDirection() {
		$thermostat = $this->thermostatAt(19, 5, array(
			'coeff_indoor_heat' => 8, 'coeff_outdoor_heat' => 1.5, 'offset_heat' => 3,
			'coeff_indoor_cool' => 50, 'coeff_outdoor_cool' => 50, 'offset_cool' => 50,
		));

		$this->assertEquals(array('power' => 8 + 22.5 + 3, 'direction' => 1), $thermostat->calculTemporalData(20));
	}

	public function testKeepsHeatingJustAboveSetpointWhenAlreadyHeating() {
		$thermostat = $this->thermostatAt(20.3, 20, array('offset_heat' => 10));

		$this->assertEqualsWithDelta(array('power' => 3, 'direction' => -1), $thermostat->calculTemporalData(20), 0.0001);

		$thermostat->setCache('lastState', 'heat');

		$this->assertEqualsWithDelta(7, $thermostat->calculTemporalData(20)['power'], 0.0001);
		$this->assertSame(1, $thermostat->calculTemporalData(20)['direction']);
	}

	public function testHeatsWhenAboveSetpointButColderOutside() {
		$thermostat = $this->thermostatAt(21, 5);

		$this->assertEquals(array('power' => 20, 'direction' => 1), $thermostat->calculTemporalData(20));
	}

	public function testOutdoorDeltaHeatThresholdIsConfigurable() {
		$thermostat = $this->thermostatAt(21, 5, array('direction::delta::heat' => 15));

		$this->assertEquals(array('power' => 0, 'direction' => -1), $thermostat->calculTemporalData(20));
	}

	public function testKeepsCoolingJustBelowSetpointWhenAlreadyCooling() {
		$thermostat = $this->thermostatAt(21.8, 30);
		$thermostat->setCache('lastState', 'cool');

		$this->assertEqualsWithDelta(array('power' => 14, 'direction' => -1), $thermostat->calculTemporalData(22), 0.0001);
	}

	public function testKeepsCoolingJustBelowSetpointEvenWhenCoolerOutside() {
		$thermostat = $this->thermostatAt(21.8, 20);
		$thermostat->setCache('lastState', 'cool');

		$this->assertEquals(array('power' => 0, 'direction' => -1), $thermostat->calculTemporalData(22));
	}

	public function testDoesNothingWhenFarAboveSetpointInHeatingDirection() {
		$thermostat = $this->thermostatAt(21.5, 5);

		$this->assertEquals(array('power' => 0, 'direction' => 1), $thermostat->calculTemporalData(20));
		$this->assertSame(1, $thermostat->getCache('temp_threshold'));
	}

	public function testDoesNothingWhenFarBelowSetpointInCoolingDirection() {
		$thermostat = $this->thermostatAt(20.5, 30);
		$thermostat->setCache('lastState', 'cool');

		$this->assertEquals(array('power' => 0, 'direction' => -1), $thermostat->calculTemporalData(22));
		$this->assertSame(1, $thermostat->getCache('temp_threshold'));
	}

	public function testResetsThresholdFlagWhenInRange() {
		$thermostat = $this->thermostatAt(19, 5);
		$thermostat->setCache('temp_threshold', 1);

		$thermostat->calculTemporalData(20);

		$this->assertSame(0, $thermostat->getCache('temp_threshold'));
	}

	public function testCapsPowerAtHundredUnlessOverfullAllowed() {
		$thermostat = $this->thermostatAt(10, -10);

		$this->assertEquals(array('power' => 100, 'direction' => 1), $thermostat->calculTemporalData(20));
		$this->assertEquals(array('power' => 160, 'direction' => 1), $thermostat->calculTemporalData(20, true));
	}

	public function testNegativePowerIsZero() {
		$thermostat = $this->thermostatAt(19, 19, array('offset_heat' => -50));

		$this->assertEquals(array('power' => 0, 'direction' => 1), $thermostat->calculTemporalData(20));
	}

	public function testMissingOutdoorTemperatureFallsBackToSetpoint() {
		$thermostat = $this->thermostatAt(19, '');

		$this->assertEquals(array('power' => 10, 'direction' => 1), $thermostat->calculTemporalData(20));
	}

	public function testReducesPowerAfterFullCycle() {
		$thermostat = $this->thermostatAt(19, 5, array('offset_nextFullCyle' => 20));
		$thermostat->setCache('last_power', 100);

		$this->assertEquals(array('power' => 20, 'direction' => 1), $thermostat->calculTemporalData(20));
		$this->assertEquals(array('power' => 40, 'direction' => 1), $thermostat->calculTemporalData(20, true));
	}

	public function testReducesPowerAfterNearlyFullCycle() {
		$thermostat = $this->thermostatAt(19, 5, array('offset_nextFullCyle' => 20, 'threshold_heathot' => 80));
		$thermostat->setCache('last_power', 90);

		$this->assertEquals(array('power' => 30, 'direction' => 1), $thermostat->calculTemporalData(20));
	}

	public function testNoReductionBelowHotThreshold() {
		$thermostat = $this->thermostatAt(19, 5, array('offset_nextFullCyle' => 20));
		$thermostat->setCache('last_power', 99);

		$this->assertEquals(array('power' => 40, 'direction' => 1), $thermostat->calculTemporalData(20));
	}
}
