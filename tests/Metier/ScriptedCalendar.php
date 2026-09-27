<?php

class ScriptedCalendar implements thermostatCalendar {

	public $available = true;
	public $next = null;
	public $inactive = array();

	public function available() {
		return $this->available;
	}

	public function nextEvent() {
		return $this->next;
	}

	public function isInactive($_calendarId) {
		return in_array($_calendarId, $this->inactive);
	}
}
