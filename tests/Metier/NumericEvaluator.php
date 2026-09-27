<?php

class NumericEvaluator implements thermostatEvaluator {

	public function evaluate($_expression) {
		return $_expression + 0;
	}
}
