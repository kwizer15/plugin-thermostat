<?php

require_once __DIR__ . '/../../Metier/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Domain\Translator;

class StatusLabelsTest extends TestCase {

	public function testLabelsAreTheStoredStatusValues() {
		$labels = new StatusLabels(new IdentityTranslator());

		$this->assertSame('Chauffage', $labels->heating());
		$this->assertSame('Climatisation', $labels->cooling());
		$this->assertSame('Arrêté', $labels->stopped());
		$this->assertSame('Suspendu', $labels->suspended());
		$this->assertSame('Calcul', $labels->computing());
		$this->assertSame('Défaillance sonde', $labels->sensorFailure());
		$this->assertSame('Défaillance chauffage', $labels->heatingFailure());
		$this->assertSame('Off', $labels->off());
		$this->assertSame('Aucun', $labels->none());
	}

	public function testEachLabelIsTranslated() {
		$labels = new StatusLabels(new class implements Translator {
			public function translate($_text) {
				return '<' . $_text . '>';
			}
		});

		$this->assertSame('<{{Chauffage}}>', $labels->heating());
		$this->assertSame('<{{Climatisation}}>', $labels->cooling());
		$this->assertSame('<{{Arrêté}}>', $labels->stopped());
		$this->assertSame('<{{Suspendu}}>', $labels->suspended());
		$this->assertSame('<{{Calcul}}>', $labels->computing());
		$this->assertSame('<{{Défaillance sonde}}>', $labels->sensorFailure());
		$this->assertSame('<{{Défaillance chauffage}}>', $labels->heatingFailure());
		$this->assertSame('<{{Off}}>', $labels->off());
		$this->assertSame('<{{Aucun}}>', $labels->none());
	}
}
