<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\Evaluator;

class NumericEvaluator implements Evaluator {

	public function evaluate($_expression) {
		return $_expression + 0;
	}
}
