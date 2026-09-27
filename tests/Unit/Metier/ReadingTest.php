<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Metier;

use Jeedom\Plugin\Thermostat\Domain\Reading;
use Jeedom\Plugin\Thermostat\Tests\Metier\FixedClock;
use PHPUnit\Framework\TestCase;

class ReadingTest extends TestCase {

	/**
	 * @dataProvider staleness
	 */
	public function testIsStale($_collectDate, $_maxMinutes, $_expected) {
		$reading = new Reading(19, $_collectDate, $_collectDate);

		$this->assertSame($_expected, $reading->isStale(new FixedClock('2026-01-15 10:00:00'), $_maxMinutes));
	}

	public function staleness() {
		return array(
			'older than delay' => array('2026-01-15 08:59:59', 60.0, true),
			'exactly at delay' => array('2026-01-15 09:00:00', 60.0, false),
			'recent' => array('2026-01-15 09:59:00', 60.0, false),
			'fractional delay exceeded' => array('2026-01-15 09:58:29', 1.5, true),
			'fractional delay not exceeded' => array('2026-01-15 09:58:30', 1.5, false),
			'no delay configured' => array('2020-01-01 00:00:00', null, false),
			'unknown collect date' => array('', 60.0, false),
		);
	}
}
