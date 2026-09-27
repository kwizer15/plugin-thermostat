<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Metier;

use Jeedom\Plugin\Thermostat\Domain\Cycle\Plan;
use Jeedom\Plugin\Thermostat\Domain\Cycle\Planner;
use PHPUnit\Framework\TestCase;

class CyclePlannerTest extends TestCase {

	private function plan($_power, $_wasHeating = false, $_stoveBoiler = 0, $_cycle = 60, $_minCycle = 10) {
		return (new Planner())->plan($_power, $_cycle, $_wasHeating, $_minCycle, $_stoveBoiler);
	}

	public function testDurationIsPowerShareOfCycle() {
		$this->assertEquals(30, $this->plan(50)->duration());
		$this->assertEquals(8, $this->plan(12.5)->duration());
	}

	public function testPartialCycleStopsAfterDuration() {
		$plan = $this->plan(50);

		$this->assertFalse($plan->isTooShort());
		$this->assertSame(Plan::STOP_AFTER, $plan->stop());
	}

	public function testFullCycleCancelsStop() {
		$this->assertSame(Plan::STOP_CANCEL, $this->plan(100)->stop());
		$this->assertSame(Plan::STOP_CANCEL, $this->plan(120)->stop());
		$this->assertSame(Plan::STOP_AFTER, $this->plan(99)->stop());
	}

	public function testStoveBoilerNeverSchedulesStop() {
		$this->assertSame(Plan::STOP_CANCEL, $this->plan(50, false, 1)->stop());
	}

	public function testZeroDurationLeavesStopUnchanged() {
		$this->assertSame(Plan::STOP_UNCHANGED, $this->plan(0.5)->stop());
	}

	public function testBelowMinimumIsTooShort() {
		$this->assertTrue($this->plan(9.9)->isTooShort());
		$this->assertFalse($this->plan(10)->isTooShort());
	}

	public function testStoveBoilerKeepsHeatingBelowMinimum() {
		$this->assertFalse($this->plan(5, true, 1)->isTooShort());
		$this->assertTrue($this->plan(5, false, 1)->isTooShort());
	}

	public function testHeatingStopsBelowOnePercent() {
		$this->assertTrue($this->plan(0.9, true, 1)->isTooShort());
		$this->assertFalse($this->plan(1, true, 1, 60, 0)->isTooShort());
		$this->assertTrue($this->plan(0.9, true, 0, 60, 0)->isTooShort());
		$this->assertFalse($this->plan(0.9, false, 0, 60, 0)->isTooShort());
	}
}
