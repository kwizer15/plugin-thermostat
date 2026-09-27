<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Domain\Configuration\Key;
use Jeedom\Plugin\Thermostat\Jeedom\CacheKey;
use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class BoundaryTest extends ThermostatTestCase {

	const RAW = array('empty' => '', 'null' => null, 'text' => 'abc', 'decimal' => '12.50', 'comma' => '12,5', 'integer' => 7, 'one' => '1');

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
			'coefficientIndoor' => array(array(1), Key::COEFF_INDOOR_HEAT, 10.0, 'number'),
			'coefficientOutdoor' => array(array(1), Key::COEFF_OUTDOOR_HEAT, 2.0, 'number'),
			'offset' => array(array(1), Key::OFFSET_HEAT, 0.0, 'number'),
			'directionDeltaHeat' => array(array(), Key::DIRECTION_DELTA_HEAT, 0.0, 'number'),
			'directionDeltaCool' => array(array(), Key::DIRECTION_DELTA_COOL, 0.0, 'number'),
			'nextFullCycleOffset' => array(array(), Key::NEXT_FULL_CYCLE_OFFSET, null, 'optional'),
			'heatHotThreshold' => array(array(), Key::HEAT_HOT_THRESHOLD, 100.0, 'number'),
			'autolearn' => array(array(), Key::AUTOLEARN, false, 'flag'),
			'coefficient' => array(array(Key::COEFF_INDOOR_HEAT), Key::COEFF_INDOOR_HEAT, 10.0, 'number'),
			'coefficient outdoor' => array(array(Key::COEFF_OUTDOOR_COOL), Key::COEFF_OUTDOOR_COOL, 2.0, 'number'),
			'learnedCount' => array(array(Key::COEFF_INDOOR_HEAT), Key::COEFF_INDOOR_HEAT_AUTOLEARN, 0, 'integer'),
			'anticipationFactor' => array(array(), Key::SMART_START_FACTOR, 1.0, 'number'),
			'anticipationCount' => array(array(), Key::SMART_START_AUTOLEARN, 0, 'integer'),
			'positiveHysteresis' => array(array(), Key::POSITIVE_HYSTERESIS, false, 'flag'),
			'hysteresisThreshold' => array(array(), Key::HYSTERESIS_THRESHOLD, 1.0, 'number'),
			'windowAlertDelay' => array(array(), Key::WINDOW_ALERT_DELAY, null, 'optional'),
			'maxTimeUpdateTemp' => array(array(), Key::MAX_TIME_UPDATE_TEMP, null, 'optional'),
			'stoveBoiler' => array(array(), Key::STOVE_BOILER, false, 'flag'),
			'minCycleDuration' => array(array(), Key::MIN_CYCLE_DURATION, 5.0, 'number'),
			'heatFailureOffset' => array(array(), Key::HEAT_FAILURE_OFFSET, 1.0, 'number'),
			'coldFailureOffset' => array(array(), Key::COLD_FAILURE_OFFSET, 1.0, 'number'),
			'indoorMinimum' => array(array(), Key::TEMPERATURE_INDOOR_MIN, null, 'optional'),
			'indoorMaximum' => array(array(), Key::TEMPERATURE_INDOOR_MAX, null, 'optional'),
		);
		return $this->cases($keys);
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
		return $this->cases($keys);
	}

	private function cases(array $_keys) {
		$cases = array();
		foreach ($_keys as $label => $spec) {
			foreach (self::RAW as $name => $raw) {
				$cases[$label . ' ' . $name] = array(strtok($label, ' '), $spec[0], $spec[1], $raw, $this->expected(isset($spec[3]) ? $spec[3] : 'raw', $raw, $spec[2]));
			}
		}
		return $cases;
	}

	private function expected($_kind, $_raw, $_default) {
		$number = is_string($_raw) ? str_replace(',', '.', $_raw) : $_raw;
		switch ($_kind) {
			case 'number':
			case 'optional':
				return is_numeric($number) ? floatval($number) : $_default;
			case 'integer':
				return is_numeric($number) ? intval(floatval($number)) : $_default;
			case 'flag':
				return $_raw == 1;
		}
		return ($_raw === '' || $_raw === null) ? $_default : $_raw;
	}
}
