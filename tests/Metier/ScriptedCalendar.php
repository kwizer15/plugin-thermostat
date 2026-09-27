<?php

class ScriptedCalendar implements thermostatCalendar {

	public $available = true;
	public $next = null;

	public function available() {
		return $this->available;
	}

	public function nextEvent() {
		return $this->next;
	}
}
