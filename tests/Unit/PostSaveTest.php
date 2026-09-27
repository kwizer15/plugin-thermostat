<?php

class PostSaveTest extends ThermostatTestCase {

	private function logicalIds(thermostat $_thermostat) {
		$return = array();
		foreach ($_thermostat->getCmd() as $cmd) {
			$return[] = $cmd->getLogicalId();
		}
		sort($return);
		return $return;
	}

	private function listenerFor(thermostat $_thermostat, $_function) {
		return listener::byClassAndFunction('thermostat', $_function, array('thermostat_id' => intval($_thermostat->getId())));
	}

	public function testCreatesCommandsForTemporalEngine() {
		$thermostat = $this->createThermostat();

		$this->assertSame(array(
			'actif', 'all_allow', 'coeff_indoor_cool', 'coeff_indoor_heat', 'coeff_outdoor_cool', 'coeff_outdoor_heat',
			'cool_only', 'deltaOrder', 'heat_only', 'lock', 'lock_state', 'mode', 'off', 'offset_cool', 'offset_heat',
			'order', 'power', 'status', 'temperature', 'temperature_outdoor', 'thermostat', 'unlock',
		), $this->logicalIds($thermostat));
	}

	public function testCreatesCommandsForHysteresisEngine() {
		$thermostat = $this->createThermostat(array('engine' => 'hysteresis'));

		$this->assertSame(array(
			'actif', 'all_allow', 'cool_only', 'heat_only', 'lock', 'lock_state', 'mode', 'off', 'offset_cool', 'offset_heat',
			'order', 'status', 'temperature', 'temperature_outdoor', 'thermostat', 'unlock',
		), $this->logicalIds($thermostat));
	}

	public function testSwitchingToHysteresisRemovesTemporalCommands() {
		$thermostat = $this->createThermostat();
		$thermostat->setConfiguration('engine', 'hysteresis');
		$thermostat->save();

		$this->assertNotContains('power', $this->logicalIds($thermostat));
		$this->assertNotContains('deltaOrder', $this->logicalIds($thermostat));
		$this->assertNotContains('coeff_indoor_heat', $this->logicalIds($thermostat));
	}

	public function testDisabledThermostatHasNoPowerCommand() {
		$thermostat = $this->createThermostat(array(), 0);

		$this->assertNotContains('power', $this->logicalIds($thermostat));
	}

	public function testDescribesCommands() {
		$thermostat = $this->createThermostat(array('order_min' => 16, 'order_max' => 24));

		$order = $this->cmdOf($thermostat, 'order');
		$this->assertSame('info', $order->getType());
		$this->assertSame('numeric', $order->getSubType());
		$this->assertSame('THERMOSTAT_SETPOINT', $order->getGeneric_type());
		$this->assertSame(16, $order->getConfiguration('minValue'));
		$this->assertSame(24, $order->getConfiguration('maxValue'));
		$this->assertSame(0, $order->getIsVisible());

		$setpoint = $this->cmdOf($thermostat, 'thermostat');
		$this->assertSame('action', $setpoint->getType());
		$this->assertSame('slider', $setpoint->getSubType());
		$this->assertSame($order->getId(), $setpoint->getValue());
		$this->assertSame(16, $setpoint->getConfiguration('minValue'));
		$this->assertSame(24, $setpoint->getConfiguration('maxValue'));

		$lockState = $this->cmdOf($thermostat, 'lock_state');
		$this->assertSame($lockState->getId(), $this->cmdOf($thermostat, 'lock')->getValue());
		$this->assertSame($lockState->getId(), $this->cmdOf($thermostat, 'unlock')->getValue());
		$this->assertSame($this->cmdOf($thermostat, 'mode')->getId(), $this->cmdOf($thermostat, 'off')->getValue());

		$this->assertSame('binary', $this->cmdOf($thermostat, 'actif')->getSubType());
		$this->assertSame('string', $this->cmdOf($thermostat, 'status')->getSubType());
		$this->assertSame('%', $this->cmdOf($thermostat, 'power')->getUnite());
		$this->assertSame(5, $this->cmdOf($thermostat, 'deltaOrder')->getConfiguration('maxValue'));
		$this->assertSame(-100, $this->cmdOf($thermostat, 'offset_heat')->getConfiguration('minValue'));
		$this->assertSame(-100, $this->cmdOf($thermostat, 'offset_cool')->getConfiguration('minValue'));
	}

	public function testUpdatesSetpointBoundsOnEverySave() {
		$thermostat = $this->createThermostat();
		$thermostat->setConfiguration('order_max', 22);
		$thermostat->save();

		$this->assertSame(22, $this->cmdOf($thermostat, 'order')->getConfiguration('maxValue'));
		$this->assertSame(22, $this->cmdOf($thermostat, 'thermostat')->getConfiguration('maxValue'));
	}

	public function testHideLockCommands() {
		$thermostat = $this->createThermostat(array('hideLockCmd' => 1));

		$this->assertSame(0, $this->cmdOf($thermostat, 'lock')->getIsVisible());
		$this->assertSame(0, $this->cmdOf($thermostat, 'unlock')->getIsVisible());

		$thermostat->setConfiguration('hideLockCmd', 0);
		$thermostat->save();

		$this->assertSame(1, $this->cmdOf($thermostat, 'lock')->getIsVisible());
	}

	public function testUserSettingsOnExistingCommandsAreKept() {
		$thermostat = $this->createThermostat();
		$this->cmdOf($thermostat, 'status')->setName('État')->setIsVisible(0)->save();
		$this->cmdOf($thermostat, 'all_allow')->setName('Tout')->setIsVisible(1)->save();

		$thermostat->save();

		$this->assertSame('État', $this->cmdOf($thermostat, 'status')->getName());
		$this->assertSame(0, $this->cmdOf($thermostat, 'status')->getIsVisible());
		$this->assertSame('Tout autorisé', $this->cmdOf($thermostat, 'all_allow')->getName());
		$this->assertSame(0, $this->cmdOf($thermostat, 'all_allow')->getIsVisible());
	}

	public function testTemperatureCommandsPointToFirstInfoCommand() {
		$action = $this->actuator();
		$indoor = $this->sensor(19.5);
		$otherIndoor = $this->sensor(20.5);
		$outdoor = $this->sensor(4.2);
		$thermostat = $this->createThermostat(array(
			'temperature_indoor' => '#' . $action->getId() . '# + #999# + #' . $indoor->getId() . '# + #' . $otherIndoor->getId() . '#',
			'temperature_outdoor' => '#' . $outdoor->getId() . '#',
		));

		$this->assertSame('#' . $indoor->getId() . '#', $this->cmdOf($thermostat, 'temperature')->getValue());
		$this->assertSame('#' . $outdoor->getId() . '#', $this->cmdOf($thermostat, 'temperature_outdoor')->getValue());
	}

	public function testInitializesEmptyTemperaturesFromExpression() {
		$indoor = $this->sensor(19.46);
		$outdoor = $this->sensor(4.24);
		$thermostat = $this->createThermostat(array(
			'temperature_indoor' => '#' . $indoor->getId() . '#',
			'temperature_outdoor' => '#' . $outdoor->getId() . '#',
		));

		$this->assertSame(19.5, $this->valueOf($thermostat, 'temperature'));
		$this->assertSame(4.2, $this->valueOf($thermostat, 'temperature_outdoor'));
	}

	public function testDoesNotOverwriteKnownTemperature() {
		$indoor = $this->sensor(19);
		$thermostat = $this->createThermostat(array('temperature_indoor' => '#' . $indoor->getId() . '#'));
		$this->setInfo($indoor, 21);

		$thermostat->save();

		$this->assertSame(19.0, $this->valueOf($thermostat, 'temperature'));
	}

	public function testConsumptionCreatesPerformanceCommandAndListener() {
		$consumption = $this->sensor(12);
		$thermostat = $this->createThermostat(array('consumption' => '#' . $consumption->getId() . '# * 2'));

		$performance = $this->cmdOf($thermostat, 'performance');
		$this->assertSame('kWh/DJU', $performance->getUnite());
		$this->assertSame('max', $performance->getConfiguration('historizeMode'));
		$this->assertSame('high::day', $performance->getDisplay('groupingType'));
		$listener = $this->listenerFor($thermostat, 'updatePerformance');
		$this->assertSame(array('#' . $consumption->getId() . '#', '#' . $this->cmdOf($thermostat, 'temperature_outdoor')->getId() . '#'), $listener->getEvent());
	}

	public function testCustomCommandMirrorsSourceCommand() {
		$humidity = $this->sensor(55);
		$humidity->setName('Humidité')->setUnite('%')->setGeneric_type('HUMIDITY')->save();
		$thermostat = $this->createThermostat(array('customCmd' => '#' . $humidity->getId() . '#'));

		$custom = $this->cmdOf($thermostat, 'customCmd');
		$this->assertSame('Humidité', $custom->getName());
		$this->assertSame('numeric', $custom->getSubType());
		$this->assertSame('%', $custom->getUnite());
		$this->assertSame('HUMIDITY', $custom->getGeneric_type());
		$this->assertSame(55.0, $custom->execCmd());

		$thermostat->setConfiguration('customCmd', '');
		$thermostat->save();

		$this->assertFalse($this->cmdOf($thermostat, 'customCmd'));
	}

	public function testSynchronizesModeCommands() {
		$thermostat = $this->createThermostat(array('existingMode' => array(
			array('name' => 'Confort', 'actions' => array(), 'isVisible' => 1),
			array('name' => 'Eco', 'actions' => array(), 'isVisible' => 0),
		)));
		$modes = $thermostat->getCmd(null, 'modeAction', null, true);
		$this->assertCount(2, $modes);
		$this->assertSame('Confort', $modes[0]->getName());
		$this->assertSame(1, $modes[0]->getIsVisible());
		$this->assertSame(0, $modes[1]->getIsVisible());
		$this->assertSame($this->cmdOf($thermostat, 'mode')->getId(), $modes[0]->getValue());
		$this->assertSame('THERMOSTAT_SET_MODE', $modes[0]->getGeneric_type());
		$confortId = $modes[0]->getId();

		$thermostat->setConfiguration('existingMode', array(
			array('name' => 'Confort', 'actions' => array(), 'isVisible' => 0),
			array('name' => 'Nuit', 'actions' => array()),
		));
		$thermostat->save();

		$modes = $thermostat->getCmd(null, 'modeAction', null, true);
		$this->assertCount(2, $modes);
		$this->assertSame($confortId, $modes[0]->getId());
		$this->assertSame(0, $modes[0]->getIsVisible());
		$this->assertSame('Nuit', $modes[1]->getName());
	}

	public function testRegistersWindowListener() {
		$window1 = $this->sensor(0, 'binary');
		$window2 = $this->sensor(0, 'binary');
		$thermostat = $this->createThermostat(array('window' => array(
			array('cmd' => '#' . $window1->getId() . '#'),
			array('cmd' => '#' . $window2->getId() . '#'),
		)));

		$this->assertSame(
			array('#' . $window1->getId() . '#', '#' . $window2->getId() . '#'),
			$this->listenerFor($thermostat, 'window')->getEvent()
		);
	}

	public function testHysteresisListensToIndoorTemperature() {
		$indoor = $this->sensor(19);
		$thermostat = $this->createThermostat(array('engine' => 'hysteresis', 'temperature_indoor' => '(#' . $indoor->getId() . '# + #42#) / 2'));

		$this->assertSame(array('#' . $indoor->getId() . '#', '#42#'), $this->listenerFor($thermostat, 'hysteresis')->getEvent());

		$thermostat->setConfiguration('engine', 'temporal');
		$thermostat->save();

		$this->assertFalse($this->listenerFor($thermostat, 'hysteresis'));
	}

	public function testLeavingTemporalEngineStopsAndRemovesPullCron() {
		$stop = $this->actuator('stop');
		$thermostat = $this->createThermostat(array('stoping' => array($this->action($stop))));
		$thermostat->reschedule(date('Y-m-d H:i:s', strtotime('+10 min')));
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$thermostat->setConfiguration('engine', 'hysteresis');
		$thermostat->save();

		$this->assertFalse($this->cronWithOptions(array('thermostat_id' => $thermostat->getId())));
		$this->assertSame(array('#' . $stop->getId() . '#'), scenarioExpression::executedCmds());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testDisablingRemovesCronsAndListeners() {
		$window = $this->sensor(0, 'binary');
		$consumption = $this->sensor(3);
		$thermostat = $this->createThermostat(array(
			'window' => array(array('cmd' => '#' . $window->getId() . '#')),
			'consumption' => '#' . $consumption->getId() . '#',
		));
		$thermostat->reschedule(date('Y-m-d H:i:s', strtotime('+10 min')));
		$thermostat->reschedule(date('Y-m-d H:i:s', strtotime('+5 min')), true);

		$thermostat->setIsEnable(0);
		$thermostat->save();

		$this->assertSame(array(), cron::all());
		$this->assertSame(array(), listener::all());
	}
}
