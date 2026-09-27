<?php

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actions;

class RecordingActions implements Actions {

	public $executed = array();
	public $modeSetsSetpoint = false;

	public function execute($_actions, $_skipOwnCmds, $_extraOptions = array()) {
		foreach ($_actions as $action) {
			$this->executed[] = $action['cmd'] . ($_skipOwnCmds ? '' : ' (own included)') . (count($_extraOptions) > 0 ? ' ' . json_encode($_extraOptions) : '');
		}
	}

	public function applyMode($_actions, $_consigne) {
		foreach ($_actions as $action) {
			$this->executed[] = 'mode ' . $action['cmd'] . ' @' . $_consigne;
		}
		return $this->modeSetsSetpoint;
	}
}
