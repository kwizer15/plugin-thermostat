<?php

class CronTest extends ThermostatTestCase {

	/**
	 * @dataProvider repeatedStatuses
	 */
	public function testRepeatsCommandsOfCurrentStatus($_status, array $_expected) {
		$thermostat = $this->equippedThermostat(array('repeat_commande_cron' => '* * * * *'));
		$this->setValueOf($thermostat, 'status', $_status);

		thermostat::cron();

		$this->assertSame($_expected, $this->executed());
		$this->assertSame($_status, $this->valueOf($thermostat, 'status'));
	}

	public function repeatedStatuses() {
		return array(
			'heating' => array('Chauffage', array('heat')),
			'stopped' => array('Arrêté', array('stop')),
			'cooling' => array('Climatisation', array('cool')),
			'suspended' => array('Suspendu', array()),
		);
	}

	public function testRepeatNotDue() {
		$thermostat = $this->equippedThermostat(array('repeat_commande_cron' => '30 * * * *'));
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		thermostat::cron();

		$this->assertSame(array(), $this->executed());
	}

	public function testInvalidRepeatCronIsLogged() {
		$thermostat = $this->equippedThermostat(array('repeat_commande_cron' => 'n\'importe quoi'));

		thermostat::cron();

		$this->assertCount(1, log::messages('error'));
	}

	public function testIgnoresDisabledThermostats() {
		$thermostat = $this->equippedThermostat(array('repeat_commande_cron' => '* * * * *'), 19, 5, 20);
		$this->setValueOf($thermostat, 'status', 'Chauffage');
		$thermostat->setIsEnable(0);

		thermostat::cron();

		$this->assertSame(array(), $this->executed());
	}

	public function testAlertsOnceWhenWindowOpenTooLong() {
		$thermostat = $this->equippedThermostat(array('window_alertIfOpenMoreThan' => 10));
		$this->setValueOf($thermostat, 'status', 'Suspendu');
		$thermostat->setCache('window::state::open', strtotime('2026-01-15 09:49:00'));

		thermostat::cron();
		thermostat::cron();

		$this->assertSame(array('[Salon] Attention le thermostat est suspendu à cause d\'une fenêtre ouverte depuis : 11minutes'), log::messages('error'));
		$this->assertSame(1, $thermostat->getCache('alertSendForWindow'));
	}

	public function testNoAlertWhenWindowOpenRecently() {
		$thermostat = $this->equippedThermostat(array('window_alertIfOpenMoreThan' => 10));
		$this->setValueOf($thermostat, 'status', 'Suspendu');
		$thermostat->setCache('window::state::open', strtotime('2026-01-15 09:51:00'));

		thermostat::cron();

		$this->assertSame(array(), log::messages('error'));
	}

	public function testWindowAlertResetsWhenNoLongerSuspended() {
		$thermostat = $this->equippedThermostat(array('window_alertIfOpenMoreThan' => 10));
		$thermostat->setCache('window::state::open', strtotime('2026-01-15 09:49:00'));
		$thermostat->setCache('alertSendForWindow', 1);

		thermostat::cron();

		$this->assertSame(0, $thermostat->getCache('alertSendForWindow'));
	}

	public function testReschedulesMissingTemporalCronEveryTenMinutes() {
		$thermostat = $this->equippedThermostat();

		thermostat::cron();

		$this->assertSame('02 10 15 01 *', $this->cronWithOptions(array('thermostat_id' => $thermostat->getId()))->getSchedule());
	}

	public function testDoesNotRescheduleOutsideTenMinutes() {
		$this->setNow('2026-01-15 10:01:00');
		$this->equippedThermostat();

		thermostat::cron();

		$this->assertSame(array(), $this->pullCrons());
	}

	public function testKeepsValidTemporalCron() {
		$thermostat = $this->equippedThermostat();
		$thermostat->reschedule('2026-01-15 10:40:00');

		thermostat::cron();

		$this->assertSame('40 10 15 01 *', $this->cronWithOptions(array('thermostat_id' => $thermostat->getId()))->getSchedule());
	}

	public function testReplacesInvalidTemporalCron() {
		$thermostat = $this->equippedThermostat();
		$thermostat->reschedule('2026-01-15 10:40:00');
		$this->cronWithOptions(array('thermostat_id' => $thermostat->getId()))->setSchedule('invalide')->save();

		thermostat::cron();

		$this->assertSame('02 10 15 01 *', $this->cronWithOptions(array('thermostat_id' => $thermostat->getId()))->getSchedule());
	}

	public function testHysteresisCronRefreshesTemperatureAndRuns() {
		$indoor = $this->sensor(20);
		$thermostat = $this->equippedThermostat(array(
			'engine' => 'hysteresis',
			'hysteresis_cron' => '* * * * *',
			'temperature_indoor' => '#' . $indoor->getId() . '#',
		));
		$this->setInfo($indoor, 18);

		thermostat::cron();

		$this->assertSame(18.0, $this->valueOf($thermostat, 'temperature'));
		$this->assertSame(array('heat'), $this->executed());
	}

	public function testStaleSensorFailureOnce() {
		$thermostat = $this->equippedThermostat(array('maxTimeUpdateTemp' => 30));
		$this->setValueOf($thermostat, 'temperature', 19, '2026-01-15 09:29:00');

		thermostat::cron();
		thermostat::cron();

		$this->assertSame(array('failure'), $this->executed());
		$this->assertCount(1, log::messages('error'));
		$this->assertSame(1, $thermostat->getCache('temp_threshold'));
	}

	public function testTemperatureBelowMinimumIsFailure() {
		$thermostat = $this->equippedThermostat(array('temperature_indoor_min' => 12), 11);

		thermostat::cron();

		$this->assertSame(array('failure'), $this->executed());
		$this->assertSame(array('[Salon] Attention la température intérieure est en dessous du seuil autorisé : 11'), log::messages('error'));
	}

	public function testTemperatureAboveMaximumIsFailure() {
		$thermostat = $this->equippedThermostat(array('temperature_indoor_max' => 28), 29);

		thermostat::cron();

		$this->assertSame(array('failure'), $this->executed());
		$this->assertSame(array('[Salon] Attention la température intérieure est au dessus du seuil autorisé : 29'), log::messages('error'));
	}

	public function testTemperatureBackInRangeResetsFlag() {
		$thermostat = $this->equippedThermostat(array('temperature_indoor_min' => 12, 'temperature_indoor_max' => 28));
		$thermostat->setCache('temp_threshold', 1);

		thermostat::cron();

		$this->assertSame(0, $thermostat->getCache('temp_threshold'));
		$this->assertSame(array(), $this->executed());
	}

	public function testModeOffSkipsSensorChecks() {
		$thermostat = $this->equippedThermostat(array('temperature_indoor_min' => 12), 11);
		$this->setValueOf($thermostat, 'mode', 'off');

		thermostat::cron();

		$this->assertSame(array(), log::messages('error'));
	}
}
