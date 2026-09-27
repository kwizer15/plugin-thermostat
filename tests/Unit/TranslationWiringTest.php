<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use Jeedom\Plugin\Thermostat\Jeedom\Translator;
use Jeedom\Plugin\Thermostat\Tests\ThermostatTestCase;

class TranslationWiringTest extends ThermostatTestCase {

	public function testTranslatorSendsEachMarkedTextWithItsFile() {
		\fakeTranslation::$texts = array('Chauffage' => 'Heating');

		$text = (new Translator('/plugin/core/src/Domain/Fichier.php'))->translate('{{Chauffage}} : {{Arrêté}}');

		$this->assertSame('Heating : Arrêté', $text);
		$this->assertSame(array(array('Chauffage', '/plugin/core/src/Domain/Fichier.php'), array('Arrêté', '/plugin/core/src/Domain/Fichier.php')), \fakeTranslation::$calls);
	}

	public function testEveryTranslationComesFromTheFileThatMarksIt() {
		$window = $this->sensor(0, 'binary');
		$temporal = $this->equippedThermostat(array('window' => array(array('cmd' => '#' . $window->getId() . '#')), 'temperature_indoor_min' => 25));
		$hysteresis = $this->equippedThermostat(array('engine' => 'hysteresis'), 18);
		\fakeTranslation::$calls = array();

		\thermostat::temporal(array('thermostat_id' => $temporal->getId()));
		\thermostat::hysteresis(array('thermostat_id' => $hysteresis->getId()));
		$this->setInfo($window, 1);
		\thermostat::window(array('thermostat_id' => $temporal->getId(), 'event_id' => $window->getId(), 'value' => 1));
		\thermostat::cron();
		$this->cmdOf($temporal, 'off')->execCmd();

		$this->assertGreaterThan(20, count(\fakeTranslation::$calls));
		foreach (\fakeTranslation::$calls as $call) {
			$this->assertSame(realpath($call[1]), $call[1], 'fichier de ' . $call[0]);
			$source = file_get_contents($call[1]);
			$marked = strpos($source, '{{' . $call[0] . '}}') !== false || strpos($source, var_export($call[0], true)) !== false || strpos($source, '"' . $call[0] . '"') !== false;
			$this->assertTrue($marked, $call[0] . ' marqué dans ' . basename($call[1]));
		}
	}
}
