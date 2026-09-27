<?php

namespace Jeedom\Plugin\Thermostat\Tests\Metier;

use Jeedom\Plugin\Thermostat\Domain\SmartStart\Calendar;

class ScriptedCalendar implements Calendar {

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
