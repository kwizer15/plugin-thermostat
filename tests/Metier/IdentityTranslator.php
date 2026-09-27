<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\Translator;

class IdentityTranslator implements Translator {

	public function translate($_text) {
		return preg_replace('/\{\{(.*?)\}\}/s', '$1', $_text);
	}
}
