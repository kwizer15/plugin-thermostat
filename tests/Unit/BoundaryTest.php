<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Domain\Configuration\Key;
use Jeedom\Plugin\Thermostat\Jeedom\CacheKey;
use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class BoundaryTest extends ThermostatTestCase {

	const RAW = array('empty' => '', 'null' => null, 'text' => 'abc', 'decimal' => '12.50', 'comma' => '12,5', 'integer' => 7);

	/**
	 * @dataProvider settings
	 */
	public function testSetting($_method, array $_arguments, $_key, $_raw, $_expected) {
		$thermostat = new \thermostat();
		$thermostat->setConfiguration($_key, $_raw);

		$this->assertSame($_expected, call_user_func_array(array($thermostat->assembly()->settings(), $_method), $_arguments));
	}

	public function settings() {
		$keys = array(
			'coefficientIndoor' => array(array(1), Key::COEFF_INDOOR_HEAT, ''),
			'coefficientOutdoor' => array(array(1), Key::COEFF_OUTDOOR_HEAT, ''),
			'offset' => array(array(1), Key::OFFSET_HEAT, ''),
			'directionDeltaHeat' => array(array(), Key::DIRECTION_DELTA_HEAT, 0),
			'directionDeltaCool' => array(array(), Key::DIRECTION_DELTA_COOL, 0),
			'nextFullCycleOffset' => array(array(), Key::NEXT_FULL_CYCLE_OFFSET, ''),
			'heatHotThreshold' => array(array(), Key::HEAT_HOT_THRESHOLD, 100),
			'autolearn' => array(array(), Key::AUTOLEARN, ''),
			'coefficient' => array(array(Key::COEFF_INDOOR_HEAT), Key::COEFF_INDOOR_HEAT, ''),
			'learnedCount' => array(array(Key::COEFF_INDOOR_HEAT), Key::COEFF_INDOOR_HEAT_AUTOLEARN, ''),
			'anticipationFactor' => array(array(), Key::SMART_START_FACTOR, 1),
			'anticipationCount' => array(array(), Key::SMART_START_AUTOLEARN, 0),
			'positiveHysteresis' => array(array(), Key::POSITIVE_HYSTERESIS, 0),
			'hysteresisThreshold' => array(array(), Key::HYSTERESIS_THRESHOLD, 1),
			'windowAlertDelay' => array(array(), Key::WINDOW_ALERT_DELAY, ''),
			'maxTimeUpdateTemp' => array(array(), Key::MAX_TIME_UPDATE_TEMP, ''),
			'stoveBoiler' => array(array(), Key::STOVE_BOILER, ''),
			'minCycleDuration' => array(array(), Key::MIN_CYCLE_DURATION, 5),
			'heatFailureOffset' => array(array(), Key::HEAT_FAILURE_OFFSET, 1),
			'coldFailureOffset' => array(array(), Key::COLD_FAILURE_OFFSET, 1),
			'indoorMinimum' => array(array(), Key::TEMPERATURE_INDOOR_MIN, ''),
			'indoorMaximum' => array(array(), Key::TEMPERATURE_INDOOR_MAX, ''),
		);
		$cases = array();
		foreach ($keys as $method => $spec) {
			foreach (self::RAW as $name => $raw) {
				$cases[$method . ' ' . $name] = array($method, $spec[0], $spec[1], $raw, ($raw === '' || $raw === null) ? $spec[2] : $raw);
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider memory
	 */
	public function testMemory($_method, array $_arguments, $_key, $_raw, $_expected) {
		$thermostat = new \thermostat();
		$thermostat->setCache($_key, $_raw);

		$this->assertSame($_expected, call_user_func_array(array($thermostat->assembly()->memory(), $_method), $_arguments));
	}

	public function memory() {
		$keys = array(
			'lastPower' => array(array(), CacheKey::LAST_POWER, 0),
			'lastOrder' => array(array(), CacheKey::LAST_ORDER, 0),
			'lastTempIn' => array(array(), CacheKey::LAST_TEMP_IN, 0),
			'consecutiveFailures' => array(array(), CacheKey::CONSECUTIVE_FAILURES, 0),
			'temperatureAlert' => array(array(), CacheKey::TEMPERATURE_ALERT, 0),
			'windowState' => array(array(7), CacheKey::WINDOW_STATE_PREFIX . '7', 0),
			'openSince' => array(array(), CacheKey::WINDOW_OPEN_SINCE, -1),
			'alertSent' => array(array(), CacheKey::WINDOW_ALERT_SENT, 0),
			'deltaOrder' => array(array(), CacheKey::DELTA_ORDER, 0),
		);
		$cases = array();
		foreach ($keys as $method => $spec) {
			foreach (self::RAW as $name => $raw) {
				$cases[$method . ' ' . $name] = array($method, $spec[0], $spec[1], $raw, ($raw === '' || $raw === null) ? $spec[2] : $raw);
			}
		}
		return $cases;
	}
}
