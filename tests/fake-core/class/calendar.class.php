<?php

class calendar extends eqLogic {
}

class calendar_event {

	private static $events = array();

	private $id;
	private $eqLogic;
	private $cmd_param = array('start' => array(), 'end' => array());
	private $nextOccurrences = array();

	public static function reset() {
		self::$events = array();
	}

	public static function create($_calendar, array $_start, array $_end, array $_nextOccurrences) {
		$event = new self();
		$event->id = count(self::$events) + 1;
		$event->eqLogic = $_calendar;
		$event->cmd_param = array('start' => $_start, 'end' => $_end);
		$event->nextOccurrences = $_nextOccurrences;
		self::$events[] = $event;
		return $event;
	}

	public static function searchByCmd($_cmd_id) {
		$return = array();
		foreach (self::$events as $event) {
			foreach (array_merge($event->cmd_param['start'], $event->cmd_param['end']) as $action) {
				if (strpos($action['cmd'], '#' . $_cmd_id . '#') !== false) {
					$return[] = $event;
					break;
				}
			}
		}
		return $return;
	}

	public function getId() {
		return $this->id;
	}

	public function getEqLogic() {
		return $this->eqLogic;
	}

	public function getCmd_param($_key = '', $_default = '') {
		return isset($this->cmd_param[$_key]) ? $this->cmd_param[$_key] : $_default;
	}

	public function nextOccurrence($_position = null, $_details = false) {
		$key = ($_position === null) ? 'null' : $_position;
		return isset($this->nextOccurrences[$key]) ? $this->nextOccurrences[$key] : array('date' => '');
	}
}
