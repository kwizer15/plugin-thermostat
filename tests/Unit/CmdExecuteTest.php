<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class CmdExecuteTest extends ThermostatTestCase {

	private function execute(\thermostat $_thermostat, $_logicalId, $_options = null) {
		return $this->cmdOf($_thermostat, $_logicalId)->execCmd($_options);
	}

	public function testDeltaOrderIsCached() {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'deltaOrder', array('slider' => 2));

		$this->assertSame(2, $thermostat->getCache('deltaOrder'));
		$this->assertSame(array(), \eqLogic::$refreshedWidgets);
	}

	public function testLockAndUnlock() {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'lock');
		$this->assertSame(1, $this->valueOf($thermostat, 'lock_state'));
		$this->assertSame(array($thermostat->getId()), \eqLogic::$refreshedWidgets);

		$this->execute($thermostat, 'unlock');
		$this->assertSame(0, $this->valueOf($thermostat, 'lock_state'));
		$this->assertCount(1, \eqLogic::$refreshedWidgets);
	}

	public function testOffsetsAreSaved() {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'offset_heat', array('slider' => 5));
		$this->execute($thermostat, 'offset_cool', array('slider' => '-2'));
		$this->execute($thermostat, 'offset_heat', array('slider' => 'abc'));

		$this->assertSame(5, $thermostat->getConfiguration('offset_heat'));
		$this->assertSame('-2', $thermostat->getConfiguration('offset_cool'));
	}

	/**
	 * @dataProvider allowModes
	 */
	public function testAllowMode($_logicalId, $_allowMode) {
		$thermostat = $this->equippedThermostat(array('allow_mode' => 'x'));

		$this->execute($thermostat, $_logicalId);

		$this->assertSame($_allowMode, $thermostat->getConfiguration('allow_mode'));
	}

	public function allowModes() {
		return array(
			array('heat_only', 'heat'),
			array('cool_only', 'cool'),
			array('all_allow', 'all'),
		);
	}

	/**
	 * @dataProvider allowModes
	 */
	public function testAllowModeRunsTemporalEngine($_logicalId, $_allowMode) {
		$thermostat = $this->equippedThermostat(array(), 19, 5, 20);

		$this->execute($thermostat, $_logicalId);

		$this->assertSame($_allowMode == 'cool' ? array('stop') : array('heat'), $this->executed());
	}

	public function testAllowModeRunsHysteresisEngine() {
		$thermostat = $this->equippedThermostat(array('engine' => 'hysteresis'), 18);

		$this->execute($thermostat, 'all_allow');

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testTemperatureCommandsEvaluateExpressions() {
		$indoor = $this->sensor(19.46);
		$outdoor = $this->sensor(-3.04);
		$custom = $this->sensor(55);
		$thermostat = $this->equippedThermostat(array(
			'temperature_indoor' => '#' . $indoor->getId() . '#',
			'temperature_outdoor' => '#' . $outdoor->getId() . '#',
			'customCmd' => '#' . $custom->getId() . '#',
		));

		$this->assertSame(19.5, $this->cmdOf($thermostat, 'temperature')->execute());
		$this->assertSame(-3.0, $this->cmdOf($thermostat, 'temperature_outdoor')->execute());
		$this->assertEquals(55, $this->cmdOf($thermostat, 'customCmd')->execute());
	}

	public function testOffStopsThermostat() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$this->execute($thermostat, 'off');

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Off', $this->valueOf($thermostat, 'mode'));
		$this->assertSame('Arrêté', $this->valueOf($thermostat, 'status'));
	}

	public function testLockBlocksModeOffAndSetpoint() {
		$thermostat = $this->equippedThermostat(array('existingMode' => array(array('name' => 'Eco', 'actions' => array()))));
		$this->setValueOf($thermostat, 'lock_state', 1);
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$thermostat->getCmd(null, 'modeAction', null, true)[0]->execCmd();
		$this->execute($thermostat, 'off');
		$this->execute($thermostat, 'thermostat', array('slider' => 22));

		$this->assertSame(array(), $this->executed());
		$this->assertSame('', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
		$this->assertCount(3, \eqLogic::$refreshedWidgets);
	}

	public function testLockDoesNotBlockConfigurationCommands() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'lock_state', 1);

		$this->execute($thermostat, 'heat_only');
		$this->execute($thermostat, 'deltaOrder', array('slider' => 1));

		$this->assertSame('heat', $thermostat->getConfiguration('allow_mode'));
		$this->assertSame(1, $thermostat->getCache('deltaOrder'));
	}

	public function testModeActionExecutesMode() {
		$thermostat = $this->equippedThermostat(array('existingMode' => array(array('name' => 'Eco', 'actions' => array()))));

		$thermostat->getCmd(null, 'modeAction', null, true)[0]->execCmd();

		$this->assertSame('Eco', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array('heat'), $this->executed());
	}

	public function testSetpointChangeRunsEngine() {
		$orderChanged = $this->actuator('orderChanged');
		$thermostat = $this->equippedThermostat(array('orderChange' => array($this->action($orderChanged))), 19, 5, 18);
		$this->setValueOf($thermostat, 'mode', 'Eco');

		$this->execute($thermostat, 'thermostat', array('slider' => 21));

		$this->assertSame(21.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame('Aucun', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array('orderChanged', 'heat'), $this->executed());
	}

	public function testSameSetpointDoesNotRunEngine() {
		$orderChanged = $this->actuator('orderChanged');
		$thermostat = $this->equippedThermostat(array('orderChange' => array($this->action($orderChanged))));

		$this->execute($thermostat, 'thermostat', array('slider' => 20));

		$this->assertSame(array('orderChanged'), $this->executed());
	}

	public function testSetpointFromModeKeepsMode() {
		$thermostat = $this->equippedThermostat();
		$this->setValueOf($thermostat, 'mode', 'Eco');

		$this->execute($thermostat, 'thermostat', array('slider' => 21, 'modeChange' => true));

		$this->assertSame('Eco', $this->valueOf($thermostat, 'mode'));
	}

	public function testSetpointWhileSuspended() {
		$thermostat = $this->equippedThermostat(array('orderChange' => array($this->action($this->actuator('orderChanged')))));
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		$this->execute($thermostat, 'thermostat', array('slider' => 21));

		$this->assertSame(21.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame('Aucun', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array(), $this->executed());
	}

	/**
	 * @dataProvider missingSliders
	 */
	public function testSetpointWithoutValueIsIgnored($_options) {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'thermostat', $_options);

		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame('', $this->valueOf($thermostat, 'mode'));
	}

	public function missingSliders() {
		return array(
			'no options' => array(array('title' => 'x')),
			'empty slider' => array(array('slider' => '')),
		);
	}

	public function testNonNumericSetpointIsIgnored() {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'thermostat', array('slider' => 'abc'));

		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame('', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array(), $this->executed());
	}

	public function testCommaDecimalSetpointIsAccepted() {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'thermostat', array('slider' => '20,5'));

		$this->assertSame(20.5, $this->valueOf($thermostat, 'order'));
	}

	public function testZeroSetpointIsAccepted() {
		$thermostat = $this->equippedThermostat(array('order_min' => 0));

		$this->execute($thermostat, 'thermostat', array('slider' => 0));

		$this->assertSame(0.0, $this->valueOf($thermostat, 'order'));
	}

	public function testOutOfRangeSetpointIsDroppedButEngineRuns() {
		$thermostat = $this->equippedThermostat();

		$this->execute($thermostat, 'thermostat', array('slider' => 35));

		$this->assertSame(20.0, $this->valueOf($thermostat, 'order'));
		$this->assertSame(array('heat'), $this->executed());
	}

	public function testSetpointWithHysteresisEngine() {
		$thermostat = $this->equippedThermostat(array('engine' => 'hysteresis'), 20);

		$this->execute($thermostat, 'thermostat', array('slider' => 22));

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testDoesNotRemoveUserCommands() {
		$this->assertTrue((new \thermostatCmd())->dontRemoveCmd());
	}
}
