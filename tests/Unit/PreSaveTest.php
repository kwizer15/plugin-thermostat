<?php

class PreSaveTest extends ThermostatTestCase {

	public function testAppliesDefaultsOnEmptyConfiguration() {
		$thermostat = $this->createThermostat();

		$this->assertSame(28, $thermostat->getConfiguration('order_max'));
		$this->assertSame(15, $thermostat->getConfiguration('order_min'));
		$this->assertSame(10, $thermostat->getConfiguration('coeff_indoor_heat'));
		$this->assertSame(10, $thermostat->getConfiguration('coeff_indoor_cool'));
		$this->assertSame(2, $thermostat->getConfiguration('coeff_outdoor_heat'));
		$this->assertSame(2, $thermostat->getConfiguration('coeff_outdoor_cool'));
		$this->assertSame(5, $thermostat->getConfiguration('minCycleDuration'));
		$this->assertSame(0, $thermostat->getConfiguration('offset_heat'));
		$this->assertSame(0, $thermostat->getConfiguration('offset_cool'));
		$this->assertSame(60, $thermostat->getConfiguration('cycle'));
		$this->assertSame(1, $thermostat->getConfiguration('smart_start'));
		$this->assertSame(1, $thermostat->getConfiguration('autolearn'));
		$this->assertSame(1, $thermostat->getConfiguration('coeff_indoor_cool_autolearn'));
		$this->assertSame(1, $thermostat->getConfiguration('coeff_indoor_heat_autolearn'));
		$this->assertSame(0, $thermostat->getConfiguration('coeff_outdoor_heat_autolearn'));
		$this->assertSame(0, $thermostat->getConfiguration('coeff_outdoor_cool_autolearn'));
		$this->assertSame(1, $thermostat->getCategory('heating'));
	}

	public function testKeepsExistingValues() {
		$thermostat = $this->createThermostat(array(
			'order_max' => 25,
			'order_min' => 12,
			'coeff_indoor_heat' => 7.5,
			'cycle' => 30,
			'smart_start' => 0,
			'coeff_indoor_heat_autolearn' => 12,
			'coeff_outdoor_heat_autolearn' => 3,
		));

		$this->assertSame(25, $thermostat->getConfiguration('order_max'));
		$this->assertSame(12, $thermostat->getConfiguration('order_min'));
		$this->assertSame(7.5, $thermostat->getConfiguration('coeff_indoor_heat'));
		$this->assertSame(30, $thermostat->getConfiguration('cycle'));
		$this->assertSame(0, $thermostat->getConfiguration('smart_start'));
		$this->assertSame(12, $thermostat->getConfiguration('coeff_indoor_heat_autolearn'));
		$this->assertSame(3, $thermostat->getConfiguration('coeff_outdoor_heat_autolearn'));
	}

	public function testResetsAutolearnCountersBelowOne() {
		$thermostat = $this->createThermostat(array(
			'coeff_indoor_heat_autolearn' => 0,
			'coeff_indoor_cool_autolearn' => -3,
			'coeff_outdoor_heat_autolearn' => 0.5,
			'coeff_outdoor_cool_autolearn' => -1,
		));

		$this->assertSame(1, $thermostat->getConfiguration('coeff_indoor_heat_autolearn'));
		$this->assertSame(1, $thermostat->getConfiguration('coeff_indoor_cool_autolearn'));
		$this->assertSame(0, $thermostat->getConfiguration('coeff_outdoor_heat_autolearn'));
		$this->assertSame(0, $thermostat->getConfiguration('coeff_outdoor_cool_autolearn'));
	}

	public function testNormalizesHysteresisThresholdDecimalSeparator() {
		$thermostat = $this->createThermostat(array('engine' => 'hysteresis', 'hysteresis_threshold' => '0,5'));

		$this->assertSame('0.5', $thermostat->getConfiguration('hysteresis_threshold'));
	}

	public function testHysteresisThresholdDefaultsToOne() {
		$thermostat = $this->createThermostat(array('engine' => 'hysteresis'));

		$this->assertSame('1', $thermostat->getConfiguration('hysteresis_threshold'));
	}

	public function testLeavesHysteresisThresholdUntouchedWithTemporalEngine() {
		$thermostat = $this->createThermostat(array('hysteresis_threshold' => '0,5'));

		$this->assertSame('0,5', $thermostat->getConfiguration('hysteresis_threshold'));
	}

	/**
	 * @dataProvider invalidConfigurations
	 */
	public function testRejectsInvalidConfiguration(array $_configuration, $_message) {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage($_message);

		$this->createThermostat($_configuration);
	}

	public function invalidConfigurations() {
		return array(
			'min above max' => array(array('order_min' => 30, 'order_max' => 20), 'La température de consigne minimale ne peut être supérieure à la consigne maximale'),
			'min cycle negative' => array(array('minCycleDuration' => -1), 'Le temps de chauffe minimal doit être compris entre 0% et 90%'),
			'min cycle above 90' => array(array('minCycleDuration' => 91), 'Le temps de chauffe minimal doit être compris entre 0% et 90%'),
			'cycle below 15' => array(array('cycle' => 14), 'Le temps de cycle doit être supérieur à 15 minutes'),
			'mode named off' => array(array('existingMode' => array(array('name' => 'Off', 'actions' => array()))), 'Vous ne pouvez faire un mode s\'appelant Off'),
			'mode named status' => array(array('existingMode' => array(array('name' => 'STATUS', 'actions' => array()))), 'Vous ne pouvez faire un mode s\'appelant Status'),
			'mode named thermostat' => array(array('existingMode' => array(array('name' => 'Thermostat', 'actions' => array()))), 'Vous ne pouvez faire un mode s\'appelant Thermostat'),
		);
	}

	public function testAcceptsBoundaryValues() {
		$thermostat = $this->createThermostat(array('minCycleDuration' => 90, 'cycle' => 15, 'order_min' => 20, 'order_max' => 20));

		$this->assertSame(90, $thermostat->getConfiguration('minCycleDuration'));
		$this->assertSame(15, $thermostat->getConfiguration('cycle'));
	}
}
