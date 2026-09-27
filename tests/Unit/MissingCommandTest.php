<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class MissingCommandTest extends ThermostatTestCase {

	private function withoutCommand(\thermostat $_thermostat, $_logicalId) {
		$this->cmdOf($_thermostat, $_logicalId)->remove();
		return $_thermostat;
	}

	public function testPullStopWithoutStatusIsLogged() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(), 'status');

		\thermostat::pull(array('thermostat_id' => $thermostat->getId(), 'stop' => 1));

		$this->assertSame(array('[Salon] Commande introuvable : status'), \log::messages('error'));
		$this->assertSame(array(), $this->executed());
	}

	public function testTemporalWithoutModeIsLogged() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(), 'mode');

		\thermostat::temporal(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame(array('[Salon] Commande introuvable : mode'), \log::messages('error'));
		$this->assertSame(array(), $this->executed());
	}

	public function testHysteresisWithoutSetpointIsLogged() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(array('engine' => 'hysteresis'), 18), 'order');

		\thermostat::hysteresis(array('thermostat_id' => $thermostat->getId()));

		$this->assertSame(array('[Salon] Commande introuvable : order'), \log::messages('error'));
		$this->assertSame(array(), $this->executed());
	}

	public function testWindowWithoutModeIsLogged() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->withoutCommand($this->equippedThermostat(array('window' => array(array('cmd' => '#' . $window->getId() . '#')))), 'mode');
		$this->setInfo($window, 1);

		\thermostat::window(array('thermostat_id' => $thermostat->getId(), 'event_id' => $window->getId(), 'value' => 1));

		$this->assertSame(array('[Salon] Commande introuvable : mode'), \log::messages('error'));
		$this->assertSame(array(), $this->executed());
	}

	public function testWindowTimerWithoutStatusIsLogged() {
		$window = $this->sensor(1, 'binary');
		$thermostat = $this->withoutCommand($this->equippedThermostat(array('window' => array(array('cmd' => '#' . $window->getId() . '#', 'stopTime' => 1)))), 'status');
		$thermostat->setCache('window::open::' . $window->getId() . '::datetime', '2026-01-15 10:00:00');

		\thermostat::windowTimer(array('thermostat_id' => $thermostat->getId(), 'cmd' => $window->getId(), 'phase' => 'open'));

		$this->assertSame(array('[Salon] Commande introuvable : status'), \log::messages('error'));
		$this->assertSame(array(), $this->executed());
	}

	public function testCronGoesOnWithNextThermostat() {
		$this->withoutCommand($this->equippedThermostat(array('repeat_commande_cron' => '* * * * *')), 'mode');
		$next = $this->equippedThermostat(array('repeat_commande_cron' => '* * * * *'));
		$this->setValueOf($next, 'status', 'Chauffage');
		\scenarioExpression::reset();

		\thermostat::cron();

		$this->assertSame(array('[Salon] Commande introuvable : mode'), \log::messages('error'));
		$this->assertSame(array('heat'), $this->executed());
	}

	public function testStartGoesOnWithNextThermostat() {
		$this->withoutCommand($this->equippedThermostat(), 'mode');
		$this->equippedThermostat();
		\scenarioExpression::reset();

		\thermostat::start();

		$this->assertSame(array('[Salon] Commande introuvable : mode'), \log::messages('error'));
		$this->assertSame(array('stop', 'heat'), $this->executed());
	}

	public function testHysteresisCronWithoutTemperatureIsLogged() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(array('engine' => 'hysteresis', 'hysteresis_cron' => '* * * * *')), 'temperature');

		\thermostat::cron();

		$this->assertContains('[Salon] : Commande introuvable : temperature', \log::messages('error'));
		$this->assertSame(array(), $this->executed());
	}

	public function testCommandFromUserInterfaceReportsMissingCommand() {
		$thermostat = $this->withoutCommand($this->equippedThermostat(), 'mode');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Commande introuvable : mode');

		$this->cmdOf($thermostat, 'off')->execCmd();
	}
}
