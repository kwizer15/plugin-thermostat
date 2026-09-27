<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class TemporalTest extends ThermostatTestCase {

	private function runTemporal(\thermostat $_thermostat) {
		\thermostat::temporal(array('thermostat_id' => $_thermostat->getId()));
	}

	private function pullSchedule(\thermostat $_thermostat) {
		$cron = $this->cronWithOptions(array('thermostat_id' => $_thermostat->getId()));
		return is_object($cron) ? $cron->getSchedule() : null;
	}

	private function stopSchedule(\thermostat $_thermostat) {
		$cron = $this->cronWithOptions(array('thermostat_id' => $_thermostat->getId(), 'stop' => 1));
		return is_object($cron) ? $cron->getSchedule() : null;
	}

	public function testUnknownThermostatIsIgnored() {
		\thermostat::temporal(array('thermostat_id' => 999));

		$this->assertSame(array(), \cron::all());
	}

	public function testHeatsForPowerShareOfCycle() {
		$thermostat = $this->equippedThermostat();

		$this->runTemporal($thermostat);

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame('Chauffage', $this->valueOf($thermostat, 'status'));
		$this->assertSame(1, $this->valueOf($thermostat, 'actif'));
		$this->assertSame(40.0, $this->valueOf($thermostat, 'power'));
		$this->assertSame('heat', $thermostat->getCache('lastState'));
		$this->assertSame(40.0, $thermostat->getCache('last_power'));
		$this->assertSame(20.0, $thermostat->getCache('lastOrder'));
		$this->assertSame(19.0, $thermostat->getCache('lastTempIn'));
		$this->assertSame(5.0, $thermostat->getCache('lastTempOut'));
		$this->assertSame('2026-01-15 10:54:00', $thermostat->getConfiguration('endDate'));
		$this->assertSame('59 10 15 01 *', $this->pullSchedule($thermostat));
		$this->assertSame('24 10 15 01 *', $this->stopSchedule($thermostat));
	}

	public function testPullCronIsOneShotWithTimeout() {
		$thermostat = $this->equippedThermostat(array('cycle' => 30));

		$this->runTemporal($thermostat);

		$cron = $this->cronWithOptions(array('thermostat_id' => $thermostat->getId()));
		$this->assertSame(1, $cron->getOnce());
		$this->assertSame(40, $cron->getTimeout());
		$this->assertSame('30 10 15 01 *', $cron->getSchedule());
	}

	public function testPersistsThermostatEachCycle() {
		$thermostat = $this->equippedThermostat();
		\eqLogic::$saves = array();

		$this->runTemporal($thermostat);

		$this->assertSame(array(array('id' => $thermostat->getId(), 'direct' => true)), \eqLogic::$saves);
	}

	public function testSuspendedThermostatOnlyReschedules() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		$this->runTemporal($thermostat);

		$this->assertSame(array(), $this->executed());
		$this->assertSame('59 10 15 01 *', $this->pullSchedule($thermostat));
		$this->assertNull($this->stopSchedule($thermostat));
	}

	public function testModeOffStops() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
		$this->assertSame(0, $this->valueOf($thermostat, 'actif'));
		$this->assertSame(0.0, $this->valueOf($thermostat, 'power'));
	}

	public function testModeOffAlreadyStoppedDoesNothing() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->setValueOf($thermostat, 'status', 'Arrêté');

		$this->runTemporal($thermostat);

		$this->assertSame(array(), $this->executed());
	}

	public function testStaleSensorTriggersFailureOnce() {
		$thermostat = $this->equippedThermostat(array('maxTimeUpdateTemp' => 30));
		$this->setValueOf($thermostat, 'temperature', 19, '2026-01-15 09:29:00');

		$this->runTemporal($thermostat);
		$this->runTemporal($thermostat);

		$this->assertSame(array('failure'), $this->executed());
		$this->assertCount(1, \log::messages('error'));
		$this->assertSame('Défaillance sonde', $this->valueOf($thermostat, 'status'));
		$this->assertSame(1, $thermostat->getCache('temp_threshold'));
	}

	public function testSensorUpdatedWithinDelayIsUsed() {
		$thermostat = $this->equippedThermostat(array('maxTimeUpdateTemp' => 30));
		$this->setValueOf($thermostat, 'temperature', 19, '2026-01-15 09:31:00');

		$this->runTemporal($thermostat);

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testIndoorTemperatureNeverReceived() {
		$thermostat = $this->equippedThermostat(array(), '');
		$this->cmdOf($thermostat, 'temperature')->setCache(array('value' => '', 'collectDate' => '', 'valueDate' => ''));

		$this->runTemporal($thermostat);

		$this->assertSame(array(), $this->executed());
		$this->assertCount(1, \log::messages('error'));
		$this->assertSame('Défaillance sonde', $this->valueOf($thermostat, 'status'));
	}

	public function testTooShortCycleStops() {
		$thermostat = $this->equippedThermostat(array(), 19.9, 20);

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('stop', $thermostat->getCache('lastState'));
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
		$this->assertNull($this->stopSchedule($thermostat));
		$thermostat->refresh();
		$this->assertSame('2026-01-15 10:54:00', $thermostat->getConfiguration('endDate'));
	}

	public function testStoveBoilerKeepsHeatingOnLowPower() {
		$thermostat = $this->equippedThermostat(array('stove_boiler' => 1), 19.7, 20);
		$thermostat->setCache('lastState', 'heat');

		$this->runTemporal($thermostat);

		$this->assertSame(array('heat'), $this->executed());
		$this->assertNull($this->stopSchedule($thermostat));
	}

	public function testStoveBoilerDoesNotStartOnLowPower() {
		$thermostat = $this->equippedThermostat(array('stove_boiler' => 1), 19.7, 20);

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testStoveBoilerStopsBelowOnePercent() {
		$thermostat = $this->equippedThermostat(array('stove_boiler' => 1), 19.95, 20);
		$thermostat->setCache('lastState', 'heat');

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testFullCycleSchedulesNoStop() {
		$thermostat = $this->equippedThermostat(array(), 10, -10);

		$this->runTemporal($thermostat);

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame(100.0, $this->valueOf($thermostat, 'power'));
		$this->assertNull($this->stopSchedule($thermostat));
	}

	public function testFullCycleCancelsPendingStop() {
		$thermostat = $this->equippedThermostat();
		$thermostat->reschedule('2026-01-15 10:24:00', true);
		$this->setValueOf($thermostat, 'temperature', 10);
		$this->setValueOf($thermostat, 'temperature_outdoor', -10);

		$this->runTemporal($thermostat);

		$this->assertNull($this->stopSchedule($thermostat));
	}

	public function testDirectionChangeStopsBeforeCooling() {
		$thermostat = $this->equippedThermostat(array(), 25, 30, 22);
		$thermostat->setCache('lastState', 'heat');

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop', 'cool'), $this->executed());
		$this->assertSame('Climatisation', $this->valueOf($thermostat, 'status'));
		$this->assertSame('cool', $thermostat->getCache('lastState'));
		$this->assertSame(46.0, $this->valueOf($thermostat, 'power'));
	}

	public function testCoolingNotAllowedStops() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'heat'), 25, 30, 22);

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testCoolingNotAllowedWhenAlreadyStoppedSendsNothing() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'heat'), 25, 30, 22);
		$this->setValueOf($thermostat, 'status', 'Arrêté');

		$this->runTemporal($thermostat);

		$this->assertSame(array(), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testDetectsActuatorFailureAfterTwoCycles() {
		$thermostat = $this->equippedThermostat(array('coeff_indoor_heat_autolearn' => 30), 17.5);
		$thermostat->setCache(array('lastState' => 'heat', 'lastOrder' => 20, 'lastTempIn' => 18));

		$this->runTemporal($thermostat);
		$this->assertSame(1, $thermostat->getCache('nbConsecutiveFaillure'));

		$this->setValueOf($thermostat, 'temperature', 17);
		$this->runTemporal($thermostat);

		$this->assertSame(2, $thermostat->getCache('nbConsecutiveFaillure'));
		$this->assertSame(array('heat', 'failureActuator', 'heat'), $this->executed());
		$this->assertSame(array('[Salon] Attention une défaillance du chauffage est détectée'), \log::messages('error'));
	}

	public function testActuatorFailureCounterResets() {
		$thermostat = $this->equippedThermostat(array('coeff_indoor_heat_autolearn' => 30), 19.5);
		$thermostat->setCache(array('lastState' => 'heat', 'lastOrder' => 20, 'lastTempIn' => 18, 'nbConsecutiveFaillure' => 1));

		$this->runTemporal($thermostat);

		$this->assertSame(0, $thermostat->getCache('nbConsecutiveFaillure'));
	}

	public function testLearnsIndoorHeatCoefficient() {
		$thermostat = $this->equippedThermostat();
		$thermostat->setCache(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 18));

		$this->runTemporal($thermostat);

		$this->assertSame(15.0, $thermostat->getConfiguration('coeff_indoor_heat'));
		$this->assertSame(2, $thermostat->getConfiguration('coeff_indoor_heat_autolearn'));
		$this->assertSame(15.0, $this->valueOf($thermostat, 'coeff_indoor_heat'));
		$this->assertSame(45.0, $this->valueOf($thermostat, 'power'));
	}

	public function testLearnsOutdoorHeatCoefficient() {
		$thermostat = $this->equippedThermostat(array(), 18.5);
		$thermostat->setCache(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 19));

		$this->runTemporal($thermostat);

		$this->assertEquals(3.0, $thermostat->getConfiguration('coeff_outdoor_heat'), '', 0.0001);
		$this->assertSame(1, $thermostat->getConfiguration('coeff_outdoor_heat_autolearn'));
		$this->assertEquals(3.0, $this->valueOf($thermostat, 'coeff_outdoor_heat'), '', 0.0001);
	}

	public function testLearnsIndoorCoolCoefficient() {
		$thermostat = $this->equippedThermostat(array(), 24, 30, 22);
		$thermostat->setCache(array('lastState' => 'cool', 'last_power' => 50, 'lastOrder' => 22, 'lastTempIn' => 25));

		$this->runTemporal($thermostat);

		$this->assertSame(20.0, $thermostat->getConfiguration('coeff_indoor_cool'));
		$this->assertSame(2, $thermostat->getConfiguration('coeff_indoor_cool_autolearn'));
	}

	public function testLearnsOutdoorCoolCoefficient() {
		$thermostat = $this->equippedThermostat(array(), 24.5, 30, 22);
		$thermostat->setCache(array('lastState' => 'cool', 'last_power' => 50, 'lastOrder' => 22, 'lastTempIn' => 24));

		$this->runTemporal($thermostat);

		$this->assertEquals(5.13, $thermostat->getConfiguration('coeff_outdoor_cool'), '', 0.0001);
		$this->assertSame(1, $thermostat->getConfiguration('coeff_outdoor_cool_autolearn'));
	}

	public function testAutolearnCounterIsCappedAtFifty() {
		$thermostat = $this->equippedThermostat(array('coeff_indoor_heat_autolearn' => 50));
		$thermostat->setCache(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 18));

		$this->runTemporal($thermostat);

		$this->assertSame(50, $thermostat->getConfiguration('coeff_indoor_heat_autolearn'));
		$this->assertEquals((10 * 50 + 20) / 51, $thermostat->getConfiguration('coeff_indoor_heat'), '', 0.01);
	}

	public function testNegativeLearnedCoefficientBecomesZero() {
		$thermostat = $this->equippedThermostat(array(), 25);
		$thermostat->setCache(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 26));

		$this->runTemporal($thermostat);

		$this->assertSame(0.0, $thermostat->getConfiguration('coeff_outdoor_heat'));
	}

	/**
	 * @dataProvider noLearningCases
	 */
	public function testDoesNotLearn(array $_configuration, array $_cache) {
		$thermostat = $this->equippedThermostat($_configuration);
		$thermostat->setCache(array_merge(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 18), $_cache));

		$this->runTemporal($thermostat);

		$this->assertSame(10, $thermostat->getConfiguration('coeff_indoor_heat'));
	}

	public function noLearningCases() {
		return array(
			'autolearn disabled' => array(array('autolearn' => 0), array()),
			'previous cycle full' => array(array(), array('last_power' => 100)),
			'previous cycle empty' => array(array(), array('last_power' => 0)),
			'cycle not finished' => array(array('endDate' => '2026-01-15 10:30:00'), array()),
		);
	}

	public function testLearningOnlyOncePerCycle() {
		$thermostat = $this->equippedThermostat();
		$thermostat->setCache(array('lastState' => 'heat', 'last_power' => 50, 'lastOrder' => 20, 'lastTempIn' => 18));
		$this->runTemporal($thermostat);

		$this->setValueOf($thermostat, 'temperature', 19.5);
		$this->runTemporal($thermostat);

		$this->assertSame(2, $thermostat->getConfiguration('coeff_indoor_heat_autolearn'));
	}

	public function testDeltaOrderRetriesAboveSetpoint() {
		$thermostat = $this->equippedThermostat();
		$thermostat->setCache('deltaOrder', 1);

		$this->runTemporal($thermostat);

		$this->assertSame(46.0, $this->valueOf($thermostat, 'power'));
		$this->assertSame(20.0, $thermostat->getCache('lastOrder'));
	}

	public function testDeltaOrderKeepsZeroPower() {
		$thermostat = $this->equippedThermostat(array(), 21);
		$thermostat->setCache('deltaOrder', 1);

		$this->runTemporal($thermostat);

		$this->assertSame(array('stop'), $this->executed());
	}
}
