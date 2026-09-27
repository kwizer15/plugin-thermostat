<?php

class PullTest extends ThermostatTestCase {

	private function calendar($_isEnable = 1, $_state = 1) {
		$calendar = new calendar();
		$calendar->setName('Agenda');
		$calendar->setEqType_name('calendar');
		$calendar->setIsEnable($_isEnable);
		$calendar->save();
		if ($_state !== null) {
			$state = new cmd();
			$state->setEqLogic_id($calendar->getId());
			$state->setLogicalId('state');
			$state->setType('info');
			$state->setSubType('binary');
			$state->save();
			$this->setInfo($state, $_state);
		}
		return $calendar;
	}

	private function smartOptions(thermostat $_thermostat, array $_next) {
		return array('thermostat_id' => intval($_thermostat->getId()), 'smartThermostat' => 1, 'next' => $_next);
	}

	public function testUnknownThermostatRemovesCronAndThrows() {
		$cron = new cron();
		$cron->setClass('thermostat')->setFunction('pull')->setOption(array('thermostat_id' => 999))->save();

		try {
			thermostat::pull(array('thermostat_id' => 999));
			$this->fail('Exception attendue');
		} catch (Exception $e) {
			$this->assertSame('Thermostat ID non trouvé : 999. Tâche supprimée', $e->getMessage());
		}
		$this->assertSame(array(), cron::all());
	}

	public function testOtherEngineRemovesCron() {
		$thermostat = $this->equippedThermostat(array('engine' => 'hysteresis'));
		$cron = new cron();
		$cron->setClass('thermostat')->setFunction('pull')->setOption(array('thermostat_id' => intval($thermostat->getId())))->save();

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId())));

		$this->assertSame(array(), cron::all());
		$this->assertSame(array(), $this->executed());
	}

	public function testRunsTemporalEngine() {
		$thermostat = $this->equippedThermostat();

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId())));

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testStopOptionStopsThermostat() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId()), 'stop' => 1));

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testStopOptionIgnoredWhenSuspended() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId()), 'stop' => 1));

		$this->assertSame(array(), $this->executed());
	}

	public function testSmartStartSendsSetpoint() {
		$thermostat = $this->equippedThermostat(array(), 19, 5, 17);
		$options = $this->smartOptions($thermostat, array('type' => 'thermostat', 'consigne' => 21, 'date' => '2026-01-15 11:00:00'));
		$cron = new cron();
		$cron->setClass('thermostat')->setFunction('pull')->setOption($options)->save();

		thermostat::pull($options);

		$this->assertSame(21.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame('Aucun', $this->valueOf($thermostat, 'mode'));
		$this->assertFalse($this->cronWithOptions($options));
		$this->assertSame(array('heat'), $this->executed());
	}

	public function testSmartStartExecutesMode() {
		$heater = $this->actuator('comfort');
		$thermostat = $this->equippedThermostat(array('existingMode' => array(
			array('name' => 'Confort', 'actions' => array($this->action($heater))),
		)));
		$mode = $thermostat->getCmd(null, 'modeAction', null, true)[0];

		thermostat::pull($this->smartOptions($thermostat, array('type' => 'mode', 'cmd' => $mode->getId(), 'consigne' => 21, 'date' => '2026-01-15 11:00:00')));

		$this->assertSame('Confort', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array('comfort', 'heat'), $this->executed());
	}

	public function testSmartStartIgnoredWhenLocked() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'lock_state', 1);

		thermostat::pull($this->smartOptions($thermostat, array('type' => 'thermostat', 'consigne' => 21, 'date' => '2026-01-15 11:00:00')));

		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
	}

	public function testSmartStartIgnoredWhenDisabled() {
		$thermostat = $this->equippedThermostat(array('smart_start' => 0));

		thermostat::pull($this->smartOptions($thermostat, array('type' => 'thermostat', 'consigne' => 21, 'date' => '2026-01-15 11:00:00')));

		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
	}

	/**
	 * @dataProvider inactiveCalendars
	 */
	public function testSmartStartIgnoredWhenCalendarInactive($_isEnable, $_state) {
		$thermostat = $this->equippedThermostat();
		$calendar = $this->calendar($_isEnable, $_state);

		thermostat::pull($this->smartOptions($thermostat, array('type' => 'thermostat', 'consigne' => 21, 'date' => '2026-01-15 11:00:00', 'calendar_id' => $calendar->getId())));

		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
	}

	public function inactiveCalendars() {
		return array(
			'disabled' => array(0, 1),
			'state off' => array(1, 0),
		);
	}

	public function testSmartStartWithActiveCalendar() {
		$thermostat = $this->equippedThermostat();
		$calendar = $this->calendar(1, null);

		thermostat::pull($this->smartOptions($thermostat, array('type' => 'thermostat', 'consigne' => 21, 'date' => '2026-01-15 11:00:00', 'calendar_id' => $calendar->getId())));

		$this->assertSame(21.0, $this->valueOf($thermostat, 'order'));
	}
}
