<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class HysteresisTest extends ThermostatTestCase {

	private function hysteresisThermostat(array $_configuration = array(), $_indoor = 20) {
		return $this->equippedThermostat(array_merge(array('engine' => 'hysteresis'), $_configuration), $_indoor);
	}

	private function runHysteresis(\thermostat $_thermostat) {
		\thermostat::hysteresis(array('thermostat_id' => $_thermostat->getId()));
	}

	public function testUnknownThermostatIsIgnored() {
		\thermostat::hysteresis(array('thermostat_id' => 999));

		$this->assertSame(array(), $this->executed());
	}

	public function testHeatsBelowLowThreshold() {
		$thermostat = $this->hysteresisThermostat(array(), 18.9);

		$this->runHysteresis($thermostat);

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame('Chauffage', $this->valueOf($thermostat, 'status'));
		$this->assertSame('heat', $thermostat->getCache('lastState'));
	}

	public function testCoolsAboveHighThreshold() {
		$thermostat = $this->hysteresisThermostat(array(), 21.1);

		$this->runHysteresis($thermostat);

		$this->assertSame(array('cool'), $this->executed());
		$this->assertSame('Climatisation', $this->valueOf($thermostat, 'status'));
	}

	/**
	 * @dataProvider noActionCases
	 */
	public function testDoesNothing($_indoor, $_status, $_lastState) {
		$thermostat = $this->hysteresisThermostat(array(), $_indoor);
		$this->setValueOf($thermostat, 'status', $_status);
		$thermostat->setCache('lastState', $_lastState);

		$this->runHysteresis($thermostat);

		$this->assertSame(array(), $this->executed());
	}

	public function noActionCases() {
		return array(
			'inside band' => array(20, '', ''),
			'on low threshold' => array(19, '', ''),
			'on high threshold' => array(21, '', ''),
			'already heating' => array(18, 'Chauffage', 'heat'),
			'already cooling' => array(22, 'Climatisation', 'cool'),
			'heating inside band' => array(20.5, 'Chauffage', 'heat'),
			'heat just after cooling' => array(18.5, '', 'cool'),
			'cool just after heating' => array(21.5, '', 'heat'),
			'suspended' => array(15, 'Suspendu', ''),
		);
	}

	public function testHeatsFarBelowAfterCooling() {
		$thermostat = $this->hysteresisThermostat(array(), 17.9);
		$thermostat->setCache('lastState', 'cool');

		$this->runHysteresis($thermostat);

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testStopsHeatingAboveHighThreshold() {
		$thermostat = $this->hysteresisThermostat(array(), 21.1);
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$this->runHysteresis($thermostat);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testStopsCoolingBelowLowThreshold() {
		$thermostat = $this->hysteresisThermostat(array(), 18.9);
		$this->setValueOf($thermostat, 'status', 'Climatisation');

		$this->runHysteresis($thermostat);

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testThresholdIsConfigurable() {
		$thermostat = $this->hysteresisThermostat(array('hysteresis_threshold' => '0,2'), 19.7);

		$this->runHysteresis($thermostat);

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testPositiveHysteresisHeatsBelowSetpoint() {
		$thermostat = $this->hysteresisThermostat(array('allow_mode' => 'heat', 'positiveHysteresis' => 1), 19.9);

		$this->runHysteresis($thermostat);

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testPositiveHysteresisCoolsAboveSetpoint() {
		$thermostat = $this->hysteresisThermostat(array('allow_mode' => 'cool', 'positiveHysteresis' => 1), 20.1);

		$this->runHysteresis($thermostat);

		$this->assertSame(array('cool'), $this->executed());
	}

	public function testPositiveHysteresisNeedsRestrictedMode() {
		$thermostat = $this->hysteresisThermostat(array('positiveHysteresis' => 1), 19.9);

		$this->runHysteresis($thermostat);

		$this->assertSame(array(), $this->executed());
	}

	public function testModeOffStops() {
		$thermostat = $this->hysteresisThermostat(array(), 15);
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$this->runHysteresis($thermostat);

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testModeOffAlreadyStopped() {
		$thermostat = $this->hysteresisThermostat(array(), 15);
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->setValueOf($thermostat, 'status', 'Arrêté');

		$this->runHysteresis($thermostat);

		$this->assertSame(array(), $this->executed());
	}

	public function testStaleSensorTriggersFailureOnce() {
		$thermostat = $this->hysteresisThermostat(array('maxTimeUpdateTemp' => 30), 15);
		$this->setValueOf($thermostat, 'temperature', 15, '2026-01-15 09:29:00');

		$this->runHysteresis($thermostat);
		$this->runHysteresis($thermostat);

		$this->assertSame(array('failure'), $this->executed());
		$this->assertCount(1, \log::messages('error'));
		$this->assertSame('Défaillance sonde', $this->valueOf($thermostat, 'status'));
	}

	public function testHistorizesSetpointOnEachRun() {
		$thermostat = $this->hysteresisThermostat();
		\cmd::$history = array();

		$this->runHysteresis($thermostat);
		$this->runHysteresis($thermostat);

		$this->assertSame(array(
			array('cmd' => 'order', 'value' => 20.0, 'datetime' => ''),
			array('cmd' => 'order', 'value' => 20.0, 'datetime' => ''),
		), \cmd::$history);
	}

	public function testHeatingNotAllowedDoesNothing() {
		$thermostat = $this->hysteresisThermostat(array('allow_mode' => 'cool'), 18);

		$this->runHysteresis($thermostat);

		$this->assertSame(array(), $this->executed());
	}

	public function testCoolingNotAllowedDoesNothing() {
		$thermostat = $this->hysteresisThermostat(array('allow_mode' => 'heat'), 22);

		$this->runHysteresis($thermostat);

		$this->assertSame(array(), $this->executed());
	}
}
