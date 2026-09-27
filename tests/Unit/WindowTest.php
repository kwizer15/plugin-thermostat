<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class WindowTest extends ThermostatTestCase {

	private function windowThermostat(array $_windows, array $_configuration = array()) {
		return $this->equippedThermostat(array_merge(array('window' => $_windows), $_configuration));
	}

	private function windowConfig(\cmd $_sensor, array $_options = array()) {
		return array_merge(array('cmd' => '#' . $_sensor->getId() . '#'), $_options);
	}

	private function notify(\thermostat $_thermostat, \cmd $_sensor, $_value) {
		$this->setInfo($_sensor, $_value);
		\thermostat::window(array('thermostat_id' => $_thermostat->getId(), 'event_id' => $_sensor->getId(), 'value' => $_value));
	}

	public function testOpeningSuspendsThermostat() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));
		$this->setValueOf($thermostat, 'status', 'Chauffage');

		$this->notify($thermostat, $window, 1);

		$this->assertSame(array('stop'), $this->executed());
		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
		$this->assertSame(0, $this->valueOf($thermostat, 'actif'));
		$this->assertSame(strtotime(self::NOW), $thermostat->getCache('window::state::open'));
		$this->assertSame(1, $thermostat->getCache('window::state::' . $window->getId()));
	}

	public function testInvertedWindow() {
		$window = $this->sensor(1, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window, array('invert' => 1))));

		$this->notify($thermostat, $window, 0);

		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
	}

	public function testOpeningIgnoredWhenOff() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));
		$this->setValueOf($thermostat, 'mode', 'Off');

		$this->notify($thermostat, $window, 1);

		$this->assertSame(array(), $this->executed());
		$this->assertSame(1, $thermostat->getCache('window::state::' . $window->getId()));
	}

	public function testOpeningIgnoredWhenDisabled() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));
		$thermostat->setIsEnable(0);

		$this->notify($thermostat, $window, 1);

		$this->assertSame('', $thermostat->getCache('window::state::' . $window->getId()));
	}

	public function testOpeningIgnoredWhenWindowClosedAgain() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));

		\thermostat::window(array('thermostat_id' => $thermostat->getId(), 'event_id' => $window->getId(), 'value' => 1));

		$this->assertSame(array(), $this->executed());
		$this->assertSame('', $this->valueOf($thermostat, 'status'));
	}

	public function testUnrelatedEventIsIgnored() {
		$window = $this->sensor(0, 'binary');
		$other = $this->sensor(1, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));

		$this->notify($thermostat, $other, 1);

		$this->assertSame('', $this->valueOf($thermostat, 'status'));
	}

	public function testClosingResumesThermostat() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));
		$this->notify($thermostat, $window, 1);
		\scenarioExpression::reset();

		$this->notify($thermostat, $window, 0);

		$this->assertSame(array('heat'), $this->executed());
		$this->assertSame('Chauffage', $this->valueOf($thermostat, 'status'));
		$this->assertSame(-1, $thermostat->getCache('window::state::open'));
		$this->assertSame(0, $thermostat->getCache('window::state::' . $window->getId()));
		$this->assertSame(self::NOW, $thermostat->getCache('window::close::' . $window->getId() . '::datetime'));
	}

	public function testClosingResumesHysteresis() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)), array('engine' => 'hysteresis'));
		$this->setValueOf($thermostat, 'temperature', 18);
		$this->notify($thermostat, $window, 1);
		\scenarioExpression::reset();

		$this->notify($thermostat, $window, 0);

		$this->assertSame(array('heat'), $this->executed());
	}

	public function testClosingWaitsForOtherWindows() {
		$window = $this->sensor(0, 'binary');
		$other = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window), $this->windowConfig($other)));
		$this->notify($thermostat, $window, 1);
		$this->setInfo($other, 1);
		\scenarioExpression::reset();

		$this->notify($thermostat, $window, 0);

		$this->assertSame(array(), $this->executed());
		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
	}

	public function testClosingWaitsForRestartTimeOfOtherWindows() {
		$window = $this->sensor(0, 'binary');
		$other = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window), $this->windowConfig($other, array('restartTime' => 5))));
		$this->notify($thermostat, $window, 1);
		$thermostat->setCache('window::close::' . $other->getId() . '::datetime', '2026-01-15 09:57:00');
		\scenarioExpression::reset();

		$this->notify($thermostat, $window, 0);

		$this->assertSame(array(), $this->executed());
		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
	}

	public function testClosingNeverSeenOpenIsIgnored() {
		$window = $this->sensor(1, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));
		$this->setValueOf($thermostat, 'status', 'Suspendu');

		$this->notify($thermostat, $window, 0);

		$this->assertSame(array(), $this->executed());
		$this->assertSame('Suspendu', $this->valueOf($thermostat, 'status'));
	}

	public function testClosingWhenNotSuspendedOnlyForgetsWindow() {
		$window = $this->sensor(0, 'binary');
		$thermostat = $this->windowThermostat(array($this->windowConfig($window)));
		$this->setValueOf($thermostat, 'mode', 'Off');
		$this->notify($thermostat, $window, 1);

		$this->notify($thermostat, $window, 0);

		$this->assertSame(array(), $this->executed());
		$this->assertSame(0, $thermostat->getCache('window::state::' . $window->getId()));
		$this->assertSame('', $thermostat->getCache('window::close::' . $window->getId() . '::datetime'));
	}
}
