<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class ActionsTest extends ThermostatTestCase {

	public function testHeatExecutesActionsWithSetpoint() {
		$notify = $this->actuator('notify');
		$thermostat = $this->equippedThermostat(array('heating' => array($this->action($notify, array('message' => 'Consigne #slider# °C', 'title' => '#slider#')))), 19, 5, 20.5);

		$this->assertTrue($thermostat->heat());

		$this->assertSame(array('message' => 'Consigne 20.5 °C', 'title' => '20.5'), \scenarioExpression::$calls[0]['options']);
		$this->assertSame('heat', $thermostat->getCache('lastState'));
		$this->assertSame(1, $this->valueOf($thermostat, 'actif'));
	}

	public function testHeatSkipsOwnCommands() {
		$thermostat = $this->equippedThermostat();
		$thermostat->setConfiguration('heating', array(
			$this->action($this->cmdOf($thermostat, 'lock')),
			$this->action($this->heater),
		))->save();

		$thermostat->heat();

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testHeatContinuesAfterFailingAction() {
		$broken = $this->actuator('broken');
		$thermostat = $this->equippedThermostat();
		$thermostat->setConfiguration('heating', array($this->action($broken), $this->action($this->heater)))->save();
		\scenarioExpression::$failingCmds = array('#' . $broken->getId() . '#');

		$thermostat->heat();

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame(array('[Salon] Erreur lors de l\'exécution de #' . $broken->getId() . '#. Détails : Échec de #' . $broken->getId() . '#'), \log::messages('error'));
	}

	public function testHeatRefusedWhenSuspended() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		$this->assertFalse($thermostat->heat());

		$this->assertSame(array(), $this->executed());
		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
	}

	public function testHeatRefusedWhenModeOffKeepsStatus() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->setValueOf($thermostat, 'status', 'Arrêté');

		$this->assertFalse($thermostat->heat());

		$this->assertSame(array(), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testHeatNotAllowedWhenAlreadyStoppedSendsNothing() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'cool'));
		$this->setValueOf($thermostat, 'status', 'Arrêté');
		\cmd::$events = array();

		$this->assertFalse($thermostat->heat());

		$this->assertSame(array(), $this->executed());
		$this->assertSame(array(), \cmd::$events);
	}

	public function testHeatNotAllowedStops() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'cool'));

		$this->assertFalse($thermostat->heat());

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testHeatWithoutActionsStops() {
		$thermostat = $this->equippedThermostat(array('heating' => array()));

		$this->assertFalse($thermostat->heat());

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testHeatWithUnsetActionsStops() {
		$thermostat = $this->equippedThermostat();
		$thermostat->setConfiguration('heating', null)->save();

		$this->assertFalse($thermostat->heat());

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame(0, $this->valueOf($thermostat, 'actif'));
	}

	public function testHeatRepeatOnlyResendsActions() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'cool'));
		$this->setValueOf($thermostat, 'mode', 'Off');

		$this->assertTrue($thermostat->heat(true));

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame('', $thermostat->getCache('lastState'));
		$this->assertSame('', $this->valueOf($thermostat, 'actif'));
	}

	public function testCoolExecutesActions() {
		$thermostat = $this->equippedThermostat();

		$this->assertTrue($thermostat->cool());

		$this->assertSame(array('cool'), $this->executed());
		$this->assertSame('Climatisation', $this->valueOf($thermostat, 'status'));
		$this->assertSame('cool', $thermostat->getCache('lastState'));
		$this->assertSame(1, $this->valueOf($thermostat, 'actif'));
	}

	public function testCoolNotAllowedStops() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'heat'));

		$this->assertFalse($thermostat->cool());

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testCoolWithoutActionsStops() {
		$thermostat = $this->equippedThermostat(array('cooling' => array()));

		$this->assertFalse($thermostat->cool());

		$this->assertSame(array('stop'), $this->executed());
	}

	public function testCoolRefusedWhenModeOffKeepsStatus() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->setValueOf($thermostat, 'status', 'Arrêté');

		$this->assertFalse($thermostat->cool());

		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testCoolNotAllowedWhenAlreadyStoppedSendsNothing() {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'heat'));
		$this->setValueOf($thermostat, 'status', 'Arrêté');
		\cmd::$events = array();

		$this->assertFalse($thermostat->cool());

		$this->assertSame(array(), $this->executed());
		$this->assertSame(array(), \cmd::$events);
	}

	public function testStopExecutesActions() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Chauffage');
		$this->setValueOf($thermostat, 'power', 40);
		$this->setValueOf($thermostat, 'actif', 1);
		\eqLogic::$saves = array();

		$thermostat->stopThermostat();

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
		$this->assertSame(0, $this->valueOf($thermostat, 'actif'));
		$this->assertSame(0.0, $this->valueOf($thermostat, 'power'));
		$this->assertSame(array(array('id' => $thermostat->getId(), 'direct' => true)), \eqLogic::$saves);
	}

	public function testStopAlreadyStoppedDoesNothing() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Arrêté');

		$thermostat->stopThermostat();

		$this->assertSame(array(), $this->executed());
	}

	public function testStopRepeatResendsActionsAndResetsOutputs() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Chauffage');
		$this->setValueOf($thermostat, 'actif', 1);
		$this->setValueOf($thermostat, 'power', 40);
		\eqLogic::$saves = array();

		$thermostat->stopThermostat(true);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame(0, $this->valueOf($thermostat, 'actif'));
		$this->assertSame(0.0, $this->valueOf($thermostat, 'power'));
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
		$this->assertSame(array(), \eqLogic::$saves);
	}

	public function testStopAlreadyStoppedWithPowerResendsActions() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Arrêté');
		$this->setValueOf($thermostat, 'power', 40);
		\eqLogic::$saves = array();

		$thermostat->stopThermostat();

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame(0.0, $this->valueOf($thermostat, 'power'));
		$this->assertSame(array(), \eqLogic::$saves);
	}

	public function testStopForSuspensionKeepsStatus() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		$thermostat->stopThermostat(false, true);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
		$this->assertSame(0, $this->valueOf($thermostat, 'actif'));
	}

	public function testStopWithUnsetActions() {
		$thermostat = $this->equippedThermostat(array('stoping' => null));

		$thermostat->stopThermostat();

		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testOrderChangeFlagsModeChange() {
		$notify = $this->actuator('notify');
		$target = $this->actuator('target');
		$thermostat = $this->equippedThermostat(array('orderChange' => array(
			$this->action($notify, array('message' => '#slider#')),
			$this->action($target),
		)));
		$thermostat->setConfiguration('orderChange', array_merge($thermostat->getConfiguration('orderChange'), array($this->action($this->cmdOf($thermostat, 'lock')))))->save();

		$thermostat->orderChange();

		$this->assertSame(array('notify', 'target'), $this->executed());
		$this->assertSame(array('message' => '20', 'modeChange' => true), \scenarioExpression::$calls[0]['options']);
		$this->assertSame(array('modeChange' => true), \scenarioExpression::$calls[1]['options']);
	}

	/**
	 * @dataProvider blockedStates
	 */
	public function testOrderChangeBlocked($_mode, $_status) {
		$thermostat = $this->equippedThermostat(array('orderChange' => array($this->action($this->actuator('target')))));
		$this->setValueOf($thermostat, 'mode', $_mode);
		$this->setValueOf($thermostat, 'status', $_status);

		$thermostat->orderChange();

		$this->assertSame(array(), $this->executed());
	}

	public function blockedStates() {
		return array(
			'mode off' => array('Off', ''),
			'suspended' => array('', 'Suspendu'),
		);
	}

	public function testFailureExecutesActionsIncludingOwnCommands() {
		$thermostat = $this->equippedThermostat();
		$thermostat->setConfiguration('failure', array($this->action($this->failureAction), $this->action($this->cmdOf($thermostat, 'lock'))))->save();

		$thermostat->failure();

		$this->assertSame(array('failure', 'lock'), $this->executed());
		$this->assertSame('Défaillance sonde', $this->valueOf($thermostat, 'status'));
		$this->assertSame(1, $this->valueOf($thermostat, 'lock_state'));
	}

	/**
	 * @dataProvider blockedStates
	 */
	public function testFailureBlocked($_mode, $_status) {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'mode', $_mode);
		$this->setValueOf($thermostat, 'status', $_status);

		$thermostat->failure();
		$thermostat->failureActuator();

		$this->assertSame(array(), $this->executed());
		$this->assertSame($_status, $this->valueOf($thermostat, 'status'));
	}

	public function testFailureWithoutActionsKeepsStatus() {
		$thermostat = $this->equippedThermostat(array('failure' => array()));

		$thermostat->failure();

		$this->assertSame('', $this->valueOf($thermostat, 'status'));
	}

	public function testFailureActuatorExecutesActions() {
		$thermostat = $this->equippedThermostat();

		$thermostat->failureActuator();

		$this->assertSame(array('failureActuator'), $this->executed());
		$this->assertSame('Défaillance chauffage', $this->valueOf($thermostat, 'status'));
	}

	public function testExecuteModeAppliesActionsAndSetpoint() {
		$notify = $this->actuator('notify');
		$thermostat = $this->equippedThermostat(array('orderChange' => array()));
		$thermostat->setConfiguration('existingMode', array(
			array('name' => 'Eco', 'actions' => array()),
			array('name' => 'Confort', 'actions' => array(
				$this->action($this->cmdOf($thermostat, 'thermostat'), array('slider' => '21')),
				$this->action($notify, array('message' => '#slider#')),
				array('cmd' => 'log', 'options' => array('message' => 'mode')),
			)),
		))->save();

		$thermostat->executeMode('Confort');

		$this->assertSame(21.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame('Confort', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array('#' . $notify->getId() . '#', 'log', '#' . $this->heater->getId() . '#'), \scenarioExpression::executedCmds());
		$this->assertSame(array('message' => '20'), \scenarioExpression::$calls[0]['options']);
	}

	public function testExecuteModeTriggersOrderChangeOnlyWithSetpoint() {
		$thermostat = $this->equippedThermostat(array('orderChange' => array($this->action($this->actuator('orderChanged')))));
		$thermostat->setConfiguration('existingMode', array(
			array('name' => 'Absent', 'actions' => array()),
			array('name' => 'Confort', 'actions' => array($this->action($this->cmdOf($thermostat, 'thermostat'), array('slider' => '21')))),
		))->save();

		$thermostat->executeMode('Absent');
		$this->assertSame(array('heat'), $this->executed());

		\scenarioExpression::reset();
		$thermostat->executeMode('Confort');
		$this->assertSame(array('orderChanged', 'heat'), $this->executed());
	}

	public function testExecuteModeWithUnsetModes() {
		$thermostat = $this->equippedThermostat(array('existingMode' => null));

		$thermostat->executeMode('Eco');

		$this->assertSame('Eco', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array('heat'), $this->executed());
	}

	public function testExecuteModeWithHysteresisEngine() {
		$thermostat = $this->equippedThermostat(array('engine' => 'hysteresis', 'existingMode' => array(array('name' => 'Eco', 'actions' => array()))), 18);

		$thermostat->executeMode('Eco');

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame('Eco', $this->valueOf($thermostat, 'mode'));
	}
}
