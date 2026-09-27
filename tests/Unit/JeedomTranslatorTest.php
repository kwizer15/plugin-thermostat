<?php

use PHPUnit\Framework\TestCase;
use Jeedom\Plugin\Thermostat\Jeedom\Translator;

class JeedomTranslatorTest extends TestCase {

	public function testReplacesEachMarkedTextByItsTranslation() {
		$translator = new Translator(__FILE__);

		$this->assertSame('Chauffage', $translator->translate('{{Chauffage}}'));
		$this->assertSame("Pause de 5 minutes", $translator->translate('{{Pause de}} 5 {{minutes}}'));
		$this->assertSame('sans marque', $translator->translate('sans marque'));
	}
}
