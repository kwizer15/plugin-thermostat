<?php

class RecordingScheduling implements thermostatScheduling {

	public $calls = array();

	public function reschedule($_next = null, $_stop = false, $_smartThermostat = false) {
		$this->calls[] = array($_next, $_stop, $_smartThermostat);
	}
}
