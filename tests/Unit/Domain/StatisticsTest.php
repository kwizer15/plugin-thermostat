<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Domain;

use Jeedom\Plugin\Thermostat\Domain\Statistics\Statistics;
use Jeedom\Plugin\Thermostat\Tests\Double\FixedClock;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemoryHistory;
use Jeedom\Plugin\Thermostat\Tests\Double\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Double\NumericEvaluator;
use PHPUnit\Framework\TestCase;

class StatisticsTest extends TestCase {

	private $settings;
	private $history;
	private $clock;

	protected function setUp() {
		$this->clock = new FixedClock('2026-01-15 10:00:00');
		$this->settings = new InMemorySettings(array('consumption' => 26));
		$this->history = new InMemoryHistory();
	}

	private function statistics() {
		return new Statistics($this->settings, new NumericEvaluator(), $this->history, $this->clock);
	}

	public function testDjuFromOutdoorMinAndMax() {
		$this->history->statistics = array('min' => 2, 'max' => 8);

		$this->assertSame(13, $this->statistics()->dju('2026-01-10'));
		$this->assertSame(array('2026-01-10 00:00:01 / 2026-01-10 23:59:59'), $this->history->requested);
	}

	public function testDjuDefaultsToToday() {
		$this->history->statistics = array('min' => 2, 'max' => 8);

		$this->statistics()->dju();

		$this->assertSame(array('2026-01-15 00:00:01 / 2026-01-15 23:59:59'), $this->history->requested);
	}

	public function testNoDjuWithoutStatistics() {
		$this->history->statistics = null;
		$this->assertNull($this->statistics()->dju('2026-01-10'));

		$this->history->statistics = array('min' => 2);
		$this->assertNull($this->statistics()->dju('2026-01-10'));
	}

	public function testUpdatesPerformance() {
		$this->history->statistics = array('min' => 2, 'max' => 8);
		$this->history->performance = null;

		$this->statistics()->updatePerformance();

		$this->assertSame(2.0, $this->history->performance);
	}

	public function testPerformanceNeedsCommandDjuAndPositiveValue() {
		$this->history->statistics = array('min' => 2, 'max' => 8);
		$this->statistics()->updatePerformance();
		$this->assertFalse($this->history->performance);

		$this->history->performance = null;
		$this->history->statistics = array('min' => 18, 'max' => 22);
		$this->statistics()->updatePerformance();
		$this->assertNull($this->history->performance);

		$this->history->statistics = null;
		$this->statistics()->updatePerformance();
		$this->assertNull($this->history->performance);
	}

	public function testRuntimeByDay() {
		$this->history->active = array(
			array('datetime' => '2026-01-14 08:00:00', 'value' => 1),
			array('datetime' => '2026-01-14 09:30:00', 'value' => 0),
			array('datetime' => '2026-01-14 23:00:00', 'value' => 1),
			array('datetime' => '2026-01-15 01:00:00', 'value' => 0),
		);

		$runtime = $this->statistics()->runtimeByDay('2026-01-13', '2026-01-15');

		$this->assertSame(array('2026-01-13', '2026-01-14', '2026-01-15'), array_keys($runtime));
		$this->assertSame(array(1768262400000, 0), $runtime['2026-01-13']);
		$this->assertEquals(90 + 3599 / 60, $runtime['2026-01-14'][1], '', 0.0001);
		$this->assertEquals(array(1768435200000, 60), $runtime['2026-01-15']);
	}

	public function testRuntimeWithoutActiveCommandIsEmpty() {
		$this->history->active = null;

		$this->assertSame(array(), $this->statistics()->runtimeByDay('2026-01-13', '2026-01-15'));
	}
}
