<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class AdapterDefensiveTest extends ThermostatTestCase {

	private function withoutCommand(\thermostat $_thermostat, $_logicalId) {
		$this->cmdOf($_thermostat, $_logicalId)->remove();
		return $_thermostat;
	}

	private function performanceThermostat() {
		$consumption = $this->sensor(26);
		$thermostat = $this->equippedThermostat(array('consumption' => '#' . $consumption->getId() . '#'));
		\cmd::$statistics[$this->cmdOf($thermostat, 'temperature_outdoor')->getId()] = array('min' => 2, 'max' => 8);
		return $thermostat;
	}

	public function testSmartStartWithUnknownCalendarStillSendsSetpoint() {
		$thermostat = $this->equippedThermostat(array(), 19, 5, 17);

		\thermostat::pull(array('thermostat_id' => intval($thermostat->getId()), 'smartThermostat' => 1, 'next' => array('type' => 'thermostat', 'consigne' => 21, 'date' => '2026-01-15 11:00:00', 'calendar_id' => 999)));

		$this->assertSame(21.0, $this->valueOf($thermostat, 'order'));
	}

	public function testRuntimeWithoutActiveCommandIsEmpty() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(), 'actif');

		$this->assertSame(array(), $thermostat->runtimeByDay('2026-01-14', '2026-01-15'));
	}

	public function testPerformanceWithoutOutdoorTemperatureCommandIsSkipped() {
		$thermostat = $this->withoutCommand($this->performanceThermostat(), 'temperature_outdoor');

		\thermostat::updatePerformance(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame('', $this->valueOf($thermostat, 'performance'));
	}

	public function testPerformanceWithoutPerformanceCommandIsSkipped() {
		$thermostat = $this->withoutCommand($this->performanceThermostat(), 'performance');

		\thermostat::updatePerformance(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame(array(), \log::messages('error'));
	}

	public function testCommandsAreIgnoredWithoutLockState() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(), 'lock_state');
		$this->setValueOf($thermostat, 'mode', 'Confort');

		$this->cmdOf($thermostat, 'off')->execCmd();

		$this->assertSame('Confort', $this->valueOf($thermostat, 'mode'));
		$this->assertSame(array($thermostat->getId()), \eqLogic::$refreshedWidgets);
	}

	public function testFailingModeActionIsLoggedAndOthersRun() {
		$broken = $this->actuator('broken');
		$lamp = $this->actuator('lamp');
		$thermostat = $this->equippedThermostat(array('existingMode' => array(
			array('name' => 'Confort', 'actions' => array($this->action($broken), $this->action($lamp))),
		)));
		\scenarioExpression::$failingCmds = array('#' . $broken->getId() . '#');

		$thermostat->executeMode('Confort');

		$this->assertSame(array('lamp', 'heat'), $this->executed());
		$this->assertSame(array('[Salon] Erreur lors de l\'exécution de #' . $broken->getId() . '#. Détails : Échec de #' . $broken->getId() . '#'), \log::messages('error'));
	}
}
