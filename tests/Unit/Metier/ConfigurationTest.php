<?php

require_once __DIR__ . '/../../Metier/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Jeedom\Plugin\Thermostat\Domain\Configuration\Configuration;

class ConfigurationTest extends TestCase {

	private function apply(array $_values) {
		$store = new InMemoryConfigurationStore($_values);
		(new Configuration($store, new IdentityTranslator()))->apply();
		return $store;
	}

	public function testAppliesDefaultsInOrder() {
		$store = $this->apply(array());

		$this->assertSame(array(
			'order_max' => 28,
			'order_min' => 15,
			'coeff_indoor_heat' => 10,
			'coeff_indoor_cool' => 10,
			'coeff_outdoor_heat' => 2,
			'coeff_outdoor_cool' => 2,
			'minCycleDuration' => 5,
			'offset_heat' => 0,
			'offset_cool' => 0,
			'cycle' => 59,
			'smart_start' => 1,
			'autolearn' => 1,
			'coeff_indoor_cool_autolearn' => 1,
			'coeff_indoor_heat_autolearn' => 1,
			'coeff_outdoor_heat_autolearn' => 0,
			'coeff_outdoor_cool_autolearn' => 0,
		), $store->values);
		$this->assertTrue($store->heating);
	}

	public function testKeepsGivenValues() {
		$store = $this->apply(array('order_max' => 25, 'cycle' => 30, 'smart_start' => 0, 'coeff_indoor_heat_autolearn' => 12));

		$this->assertSame(25, $store->values['order_max']);
		$this->assertSame(30, $store->values['cycle']);
		$this->assertSame(0, $store->values['smart_start']);
		$this->assertSame(12, $store->values['coeff_indoor_heat_autolearn']);
	}

	public function testResetsLearningCountersBelowOne() {
		$store = $this->apply(array('coeff_indoor_heat_autolearn' => 0.5, 'coeff_outdoor_heat_autolearn' => 0.5, 'coeff_outdoor_cool_autolearn' => 3));

		$this->assertSame(1, $store->values['coeff_indoor_heat_autolearn']);
		$this->assertSame(0, $store->values['coeff_outdoor_heat_autolearn']);
		$this->assertSame(3, $store->values['coeff_outdoor_cool_autolearn']);
	}

	public function testNormalizesHysteresisThresholdOnlyForHysteresisEngine() {
		$this->assertSame('0.5', $this->apply(array('engine' => 'hysteresis', 'hysteresis_threshold' => '0,5'))->values['hysteresis_threshold']);
		$this->assertSame('1', $this->apply(array('engine' => 'hysteresis'))->values['hysteresis_threshold']);
		$this->assertSame('0,5', $this->apply(array('engine' => 'temporal', 'hysteresis_threshold' => '0,5'))->values['hysteresis_threshold']);
	}

	/**
	 * @dataProvider invalid
	 */
	public function testRejectsInvalidConfiguration(array $_values, $_message) {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage($_message);

		$this->apply($_values);
	}

	public function invalid() {
		return array(
			'min above max' => array(array('order_min' => 22, 'order_max' => 21), 'consigne minimale'),
			'negative minimum cycle' => array(array('minCycleDuration' => -1), 'temps de chauffe minimal'),
			'minimum cycle above 90' => array(array('minCycleDuration' => 91), 'temps de chauffe minimal'),
			'short cycle' => array(array('cycle' => 14), 'temps de cycle'),
			'mode off' => array(array('existingMode' => array(array('name' => 'OFF'))), 'Off'),
			'mode status' => array(array('existingMode' => array(array('name' => 'Status'))), 'Status'),
			'mode thermostat' => array(array('existingMode' => array(array('name' => 'thermostat'))), 'Thermostat'),
		);
	}

	public function testAcceptsBoundaries() {
		$store = $this->apply(array('order_min' => 20, 'order_max' => 20, 'minCycleDuration' => 90, 'cycle' => 15, 'existingMode' => array(array('name' => 'Confort'))));

		$this->assertSame(90, $store->values['minCycleDuration']);
		$this->assertSame(0, $this->apply(array('minCycleDuration' => 0))->values['minCycleDuration']);
	}
}
