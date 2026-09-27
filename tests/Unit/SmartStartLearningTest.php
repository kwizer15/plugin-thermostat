<?php

class SmartStartLearningTest extends ThermostatTestCase {

	private function pendingThermostat(array $_configuration, $_startTemperature, $_consigne, $_temperatureAtEvent, $_eventDate = '2026-01-15 10:00:00') {
		$thermostat = $this->equippedThermostat($_configuration, $_temperatureAtEvent, 5, $_consigne);
		$thermostat->setCache('smartStart', array(
			'start' => '2026-01-15 09:15:00',
			'date' => $_eventDate,
			'consigne' => $_consigne,
			'temperature' => $_startTemperature,
		));
		return $thermostat;
	}

	private function runTemporal(thermostat $_thermostat) {
		thermostat::temporal(array('thermostat_id' => $_thermostat->getId()));
	}

	public function testLearnsFromLatePreheat() {
		$thermostat = $this->pendingThermostat(array(), 18, 20, 19);

		$this->runTemporal($thermostat);

		$this->assertSame(2.0, $thermostat->getConfiguration('smart_start_factor'));
		$this->assertSame(1, $thermostat->getConfiguration('smart_start_autolearn'));
		$this->assertSame(2.0, $this->valueOf($thermostat, 'smart_start_factor'));
		$this->assertSame('', $thermostat->getCache('smartStart'));
	}

	public function testLearnsFromEarlyPreheat() {
		$thermostat = $this->pendingThermostat(array(), 18, 20, 20.5);

		$this->runTemporal($thermostat);

		$this->assertSame(0.8, $thermostat->getConfiguration('smart_start_factor'));
	}

	public function testSmoothsWithPreviousEvents() {
		$thermostat = $this->pendingThermostat(array('smart_start_factor' => 1, 'smart_start_autolearn' => 3), 18, 20, 19);

		$this->runTemporal($thermostat);

		$this->assertSame(1.25, $thermostat->getConfiguration('smart_start_factor'));
		$this->assertSame(4, $thermostat->getConfiguration('smart_start_autolearn'));
	}

	public function testCountIsCappedAtTen() {
		$thermostat = $this->pendingThermostat(array('smart_start_factor' => 1, 'smart_start_autolearn' => 10), 18, 20, 19);

		$this->runTemporal($thermostat);

		$this->assertSame(10, $thermostat->getConfiguration('smart_start_autolearn'));
		$this->assertSame(round((10 + 2) / 11, 2), $thermostat->getConfiguration('smart_start_factor'));
	}

	public function testRatioIsBoundedPerEvent() {
		$thermostat = $this->pendingThermostat(array(), 18, 21, 18.2);

		$this->runTemporal($thermostat);

		$this->assertSame(2.0, $thermostat->getConfiguration('smart_start_factor'));
	}

	public function testRatioIsBoundedBelowPerEvent() {
		$thermostat = $this->pendingThermostat(array('smart_start_factor' => 2), 19.5, 20, 21.5);

		$this->runTemporal($thermostat);

		$this->assertSame(1.0, $thermostat->getConfiguration('smart_start_factor'));
	}

	public function testFactorIsBounded() {
		$high = $this->pendingThermostat(array('smart_start_factor' => 2.5), 18, 20, 19);
		$this->runTemporal($high);
		$this->assertSame(3.0, $high->getConfiguration('smart_start_factor'));

		$low = $this->pendingThermostat(array('smart_start_factor' => 0.6), 18, 20, 22);
		$this->runTemporal($low);
		$this->assertSame(0.5, $low->getConfiguration('smart_start_factor'));
	}

	public function testWaitsForEventTime() {
		$thermostat = $this->pendingThermostat(array(), 18, 20, 19, '2026-01-15 10:30:00');

		$this->runTemporal($thermostat);

		$this->assertSame('', $thermostat->getConfiguration('smart_start_factor'));
		$this->assertSame('2026-01-15 10:30:00', $thermostat->getCache('smartStart')['date']);
	}

	/**
	 * @dataProvider notLearnableCases
	 */
	public function testForgetsWithoutLearning($_startTemperature, $_consigne, $_temperatureAtEvent, $_eventDate) {
		$thermostat = $this->pendingThermostat(array(), $_startTemperature, $_consigne, $_temperatureAtEvent, $_eventDate);

		$this->runTemporal($thermostat);

		$this->assertSame('', $thermostat->getConfiguration('smart_start_factor'));
		$this->assertSame('', $thermostat->getCache('smartStart'));
	}

	public function notLearnableCases() {
		return array(
			'small gap' => array(19.7, 20, 19.9, '2026-01-15 10:00:00'),
			'no rise' => array(18, 20, 17.8, '2026-01-15 10:00:00'),
			'event long past' => array(18, 20, 19, '2026-01-15 07:30:00'),
		);
	}

	public function testSuspendedThermostatDoesNotLearn() {
		$thermostat = $this->pendingThermostat(array(), 18, 20, 19);
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		$this->runTemporal($thermostat);

		$this->assertSame('', $thermostat->getConfiguration('smart_start_factor'));
	}

	public function testLearnedFactorIsPersisted() {
		$thermostat = $this->pendingThermostat(array(), 18, 20, 19);

		$this->runTemporal($thermostat);
		$thermostat->refresh();

		$this->assertSame(2.0, $thermostat->getConfiguration('smart_start_factor'));
	}

	public function testPullRemembersSmartStart() {
		$thermostat = $this->equippedThermostat(array(), 18.5, 5, 17);

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId()), 'smartThermostat' => 1, 'next' => array('type' => 'thermostat', 'consigne' => '21', 'date' => '2026-01-15 11:00:00')));

		$this->assertSame(array(
			'start' => self::NOW,
			'date' => '2026-01-15 11:00:00',
			'consigne' => 21,
			'temperature' => 18.5,
		), $thermostat->getCache('smartStart'));
	}

	public function testPullRemembersSmartStartForMode() {
		$thermostat = $this->equippedThermostat(array(), 18.5);
		$thermostat->setConfiguration('existingMode', array(array('name' => 'Confort', 'actions' => array($this->action($this->cmdOf($thermostat, 'thermostat'), array('slider' => '21'))))))->save();
		$mode = $thermostat->getCmd(null, 'modeAction', null, true)[0];

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId()), 'smartThermostat' => 1, 'next' => array('type' => 'mode', 'cmd' => $mode->getId(), 'consigne' => '21', 'date' => '2026-01-15 11:00:00')));

		$this->assertSame('2026-01-15 11:00:00', $thermostat->getCache('smartStart')['date']);
		$this->assertSame(21, $thermostat->getCache('smartStart')['consigne']);
	}

	public function testLockedPullRemembersNothing() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'lock_state', 1);

		thermostat::pull(array('thermostat_id' => intval($thermostat->getId()), 'smartThermostat' => 1, 'next' => array('type' => 'thermostat', 'consigne' => '21', 'date' => '2026-01-15 11:00:00')));

		$this->assertSame('', $thermostat->getCache('smartStart'));
	}
}
