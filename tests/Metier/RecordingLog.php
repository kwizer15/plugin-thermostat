<?php

class RecordingLog implements thermostatLog {

	public $lines = array();

	public function debug($_message) {
		$this->lines[] = 'debug ' . $_message;
	}

	public function error($_message) {
		$this->lines[] = 'error ' . $_message;
	}
}
