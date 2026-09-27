<?php

class GetNextStateTest extends ThermostatTestCase {

	private function calendar($_isEnable = 1) {
		plugin::install('calendar');
		$calendar = new calendar();
		$calendar->setName('Agenda');
		$calendar->setEqType_name('calendar');
		$calendar->setIsEnable($_isEnable);
		$calendar->save();
		return $calendar;
	}

	private function thermostatWithModes(array $_modes, $_indoor = 19, $_outdoor = 5) {
		$thermostat = $this->equippedThermostat(array(), $_indoor, $_outdoor);
		$existingModes = array();
		foreach ($_modes as $name => $slider) {
			$actions = ($slider === null) ? array() : array($this->action($this->cmdOf($thermostat, 'thermostat'), array('slider' => $slider)));
			$existingModes[] = array('name' => $name, 'actions' => $actions);
		}
		$thermostat->setConfiguration('existingMode', $existingModes)->save();
		return $thermostat;
	}

	private function mode(thermostat $_thermostat, $_name) {
		foreach ($_thermostat->getCmd(null, 'modeAction', null, true) as $mode) {
			if ($mode->getName() == $_name) {
				return $mode;
			}
		}
	}

	private function eventOnStart($_calendar, cmd $_cmd, $_date, array $_options = array()) {
		return calendar_event::create($_calendar, array(array('cmd' => '#' . $_cmd->getId() . '#', 'options' => $_options)), array(), array('start' => array('date' => $_date)));
	}

	private function smartCrons() {
		return cron::searchClassAndFunction('thermostat', 'pull', '"smartThermostat":1');
	}

	public function testSchedulesSmartStartBeforeModeEvent() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$calendar = $this->calendar();
		$mode = $this->mode($thermostat, 'Confort');
		$this->eventOnStart($calendar, $mode, '2026-01-15 18:00:00');

		$thermostat->getNextState();

		$crons = $this->smartCrons();
		$this->assertCount(1, $crons);
		$this->assertSame('29 17 15 01 *', $crons[0]->getSchedule());
		$next = $crons[0]->getOption()['next'];
		$this->assertSame('mode', $next['type']);
		$this->assertSame('21', $next['consigne']);
		$this->assertSame($mode->getId(), $next['cmd']);
		$this->assertSame($calendar->getId(), $next['calendar_id']);
		$this->assertSame('2026-01-15 17:29:00', $next['schedule']);
	}

	public function testPicksEarliestEvent() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$calendar = $this->calendar();
		$mode = $this->mode($thermostat, 'Confort');
		$this->eventOnStart($calendar, $mode, '2026-01-15 18:00:00');
		$this->eventOnStart($calendar, $mode, '2026-01-15 12:00:00');

		$thermostat->getNextState();

		$this->assertSame('29 11 15 01 *', $this->smartCrons()[0]->getSchedule());
	}

	public function testUsesEndPositionWhenModeOnlyEnds() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$calendar = $this->calendar();
		$mode = $this->mode($thermostat, 'Confort');
		calendar_event::create($calendar, array(), array(array('cmd' => '#' . $mode->getId() . '#')), array('end' => array('date' => '2026-01-15 20:00:00')));

		$thermostat->getNextState();

		$this->assertSame('29 19 15 01 *', $this->smartCrons()[0]->getSchedule());
	}

	public function testReplacesPreviousSmartCron() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$calendar = $this->calendar();
		$this->eventOnStart($calendar, $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');
		$thermostat->reschedule('2026-01-15 16:00:00', false, array('type' => 'mode', 'old' => 1));
		$thermostat->reschedule('2026-01-15 11:00:00');

		$thermostat->getNextState();

		$this->assertCount(1, $this->smartCrons());
		$this->assertCount(2, $this->pullCrons());
	}

	public function testNoScheduleWhenStartWouldBeTooSoon() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Confort'), '2026-01-15 10:30:00');

		$thermostat->getNextState();

		$this->assertSame(array(), $this->smartCrons());
	}

	public function testNoScheduleForShortHeating() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'), 21, 21);
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');

		$this->assertSame('', $thermostat->getNextState());
		$this->assertSame(array(), $this->smartCrons());
	}

	public function testIgnoresModeWithoutSetpoint() {
		$thermostat = $this->thermostatWithModes(array('Absent' => null));
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Absent'), '2026-01-15 18:00:00');

		$this->assertSame('', $thermostat->getNextState());
		$this->assertSame(array(), $this->smartCrons());
	}

	public function testIgnoresDisabledCalendar() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$this->eventOnStart($this->calendar(0), $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');

		$this->assertSame('', $thermostat->getNextState());
	}

	public function testRequiresActiveCalendarPlugin() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');
		plugin::install('calendar', 0);

		$this->assertSame('', $thermostat->getNextState());
		plugin::reset();
		$this->assertSame('', $thermostat->getNextState());
		$this->assertSame(array(), $this->smartCrons());
	}

	public function testOnlyForTemporalEngine() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');
		$thermostat->setConfiguration('engine', 'hysteresis');

		$this->assertSame('', $thermostat->getNextState());
	}

	public function testSetpointEventIsIgnoredWithWarnings() {
		$this->allowPhpError('Undefined variable: options');
		$this->allowPhpError('strtotime() expects parameter 1 to be string, array given');
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		calendar_event::create($this->calendar(), array(array('cmd' => '#' . $this->cmdOf($thermostat, 'thermostat')->getId() . '#', 'options' => array('slider' => 22))), array(), array('null' => array('date' => '2026-01-15 18:00:00')));

		$this->assertNull($thermostat->getNextState());
		$this->assertSame(array(), $this->smartCrons());
		$this->assertNotEmpty($this->phpErrors());
	}

	public function testSetpointEventWithoutModesCrashesTemporalEngine() {
		$this->allowPhpError('Undefined variable: mode');
		$thermostat = $this->equippedThermostat();
		$this->eventOnStart($this->calendar(), $this->cmdOf($thermostat, 'thermostat'), '2026-01-15 18:00:00', array('slider' => 22));

		try {
			thermostat::temporal(array('thermostat_id' => $thermostat->getId()));
			$this->fail('Error attendue');
		} catch (Error $e) {
			$this->assertSame('Call to a member function getId() on null', $e->getMessage());
		}
		$this->assertSame(array(), $this->executed());
		$this->assertSame('00 11 15 01 *', $this->cronWithOptions(array('thermostat_id' => $thermostat->getId()))->getSchedule());
	}

	public function testTemporalRunsSmartStartWhenEnabled() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');

		thermostat::temporal(array('thermostat_id' => $thermostat->getId()));

		$this->assertCount(1, $this->smartCrons());
	}

	public function testTemporalSkipsSmartStartWhenDisabled() {
		$thermostat = $this->thermostatWithModes(array('Confort' => '21'));
		$thermostat->setConfiguration('smart_start', 0)->save();
		$this->eventOnStart($this->calendar(), $this->mode($thermostat, 'Confort'), '2026-01-15 18:00:00');

		thermostat::temporal(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame(array(), $this->smartCrons());
	}
}
