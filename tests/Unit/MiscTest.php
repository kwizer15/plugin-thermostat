<?php

class MiscTest extends ThermostatTestCase {

	public function testStartRestartsEnabledThermostats() {
		$temporal = $this->equippedThermostat();
		$this->setValueOf($temporal, 'status', 'Chauffage');
		$off = $this->equippedThermostat();
		$this->setValueOf($off, 'mode', 'Off');
		$this->setValueOf($off, 'status', 'Chauffage');
		$hysteresis = $this->equippedThermostat(array('engine' => 'hysteresis'), 18);
		$this->setValueOf($hysteresis, 'status', 'Arrêté');
		$disabled = $this->equippedThermostat();
		$disabled->setIsEnable(0);

		thermostat::start();

		$this->assertSame(array('stop', 'heat', 'heat'), $this->executed());
		$this->assertSame('Chauffage', $this->valueOf($off, 'status'));
	}

	public function testDeadCmdListsMissingCommands() {
		$sensor = $this->sensor(19);
		$thermostat = $this->equippedThermostat(array('temperature_indoor' => '#' . $sensor->getId() . '#', 'temperature_outdoor' => '#999#'));
		$sensor->remove();

		$this->assertSame(array(
			array('detail' => 'Thermostat [Salon]', 'help' => 'Action', 'who' => '#' . $sensor->getId() . '#'),
			array('detail' => 'Thermostat [Salon]', 'help' => 'Action', 'who' => '#999#'),
		), thermostat::deadCmd());
	}

	public function testRescheduleCreatesOneShotCron() {
		$thermostat = $this->equippedThermostat(array('cycle' => 20));

		$thermostat->reschedule('2026-01-15 10:20:00');
		$thermostat->reschedule('2026-01-15 10:25:00');
		$thermostat->reschedule('2026-01-15 10:05:00', true);

		$crons = $this->pullCrons();
		$this->assertCount(2, $crons);
		$this->assertSame(array('thermostat_id' => $thermostat->getId()), $crons[0]->getOption());
		$this->assertSame('25 10 15 01 *', $crons[0]->getSchedule());
		$this->assertSame(30, $crons[0]->getTimeout());
		$this->assertSame(array('thermostat_id' => $thermostat->getId(), 'stop' => 1), $crons[1]->getOption());
	}

	public function testRemoveCleansCronsAndListeners() {
		$window = $this->sensor(0, 'binary');
		$consumption = $this->sensor(1);
		$thermostat = $this->equippedThermostat(array(
			'window' => array(array('cmd' => '#' . $window->getId() . '#')),
			'consumption' => '#' . $consumption->getId() . '#',
		));
		$thermostat->reschedule('2026-01-15 10:20:00');
		$thermostat->reschedule('2026-01-15 10:05:00', true);

		$thermostat->remove();

		$this->assertSame(array(), cron::all());
		$this->assertSame(array(), listener::all());
	}

	public function testCalculDju() {
		$thermostat = $this->equippedThermostat();
		$outdoor = $this->cmdOf($thermostat, 'temperature_outdoor');

		$this->assertNull($thermostat->calculDju());

		cmd::$statistics[$outdoor->getId()] = array('min' => 2, 'max' => 8);
		$this->assertSame(13, $thermostat->calculDju('2026-01-10'));
	}

	public function testUpdatePerformance() {
		$consumption = $this->sensor(26);
		$thermostat = $this->equippedThermostat(array('consumption' => '#' . $consumption->getId() . '#'));
		cmd::$statistics[$this->cmdOf($thermostat, 'temperature_outdoor')->getId()] = array('min' => 2, 'max' => 8);

		thermostat::updatePerformance(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame(2.0, $this->valueOf($thermostat, 'performance'));
	}

	public function testUpdatePerformanceIgnoresWarmDays() {
		$consumption = $this->sensor(26);
		$thermostat = $this->equippedThermostat(array('consumption' => '#' . $consumption->getId() . '#'));
		cmd::$statistics[$this->cmdOf($thermostat, 'temperature_outdoor')->getId()] = array('min' => 18, 'max' => 22);

		thermostat::updatePerformance(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame('', $this->valueOf($thermostat, 'performance'));
	}

	public function testUpdatePerformanceWithoutStatistics() {
		$consumption = $this->sensor(26);
		$thermostat = $this->equippedThermostat(array('consumption' => '#' . $consumption->getId() . '#'));

		thermostat::updatePerformance(array('thermostat_id' => $thermostat->getId()));
		thermostat::updatePerformance(array('thermostat_id' => 999));

		$this->assertSame('', $this->valueOf($thermostat, 'performance'));
	}

	public function testRuntimeByDay() {
		$thermostat = $this->equippedThermostat();
		cmd::$histories[$this->cmdOf($thermostat, 'actif')->getId()] = array(
			new history('2026-01-14 08:00:00', 1),
			new history('2026-01-14 09:30:00', 0),
			new history('2026-01-14 23:00:00', 1),
			new history('2026-01-15 01:00:00', 0),
		);

		$runtime = $thermostat->runtimeByDay('2026-01-13', '2026-01-15');

		$this->assertSame(array('2026-01-13', '2026-01-14', '2026-01-15'), array_keys($runtime));
		$this->assertSame(array(1768262400000, 0), $runtime['2026-01-13']);
		$this->assertSame(1768348800000, $runtime['2026-01-14'][0]);
		$this->assertEqualsWithDelta(90 + 3599 / 60, $runtime['2026-01-14'][1], 0.0001);
		$this->assertEquals(array(1768435200000, 60), $runtime['2026-01-15']);
	}
}
