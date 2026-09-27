<?php

use Jeedom\Plugin\Thermostat\Domain\Evaluator;

class NumericEvaluator implements Evaluator {

	public function evaluate($_expression) {
		return $_expression + 0;
	}
}
