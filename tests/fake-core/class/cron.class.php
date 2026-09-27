<?php

class cron {

	private static $store = array();

	private static $nextId = 1;

	private $id;
	private $class = '';
	private $function = '';
	private $option = '';
	private $schedule = '';
	private $timeout;
	private $once = 0;
	private $state = 'stop';

	public static function reset() {
		self::$store = array();
		self::$nextId = 1;
	}

	public static function all() {
		return array_values(self::$store);
	}

	public static function byClassAndFunction($_class, $_function, $_option = '') {
		foreach (self::$store as $cron) {
			if ($cron->class != $_class || $cron->function != $_function) {
				continue;
			}
			if ($_option != '' && $cron->option !== json_encode($_option, JSON_UNESCAPED_UNICODE)) {
				continue;
			}
			return $cron;
		}
		return false;
	}

	public static function searchClassAndFunction($_class, $_function, $_option = '') {
		$pattern = '/^' . str_replace('%', '.*', preg_quote('%' . $_option . '%', '/')) . '$/s';
		$return = array();
		foreach (self::$store as $cron) {
			if ($cron->class == $_class && $cron->function == $_function && ($_option == '' || preg_match($pattern, $cron->option))) {
				$return[] = $cron;
			}
		}
		return $return;
	}

	public static function convertDateToCron($_date) {
		return date('i', $_date) . ' ' . date('H', $_date) . ' ' . date('d', $_date) . ' ' . date('m', $_date) . ' *';
	}

	public function save() {
		if ($this->id === null) {
			$this->id = self::$nextId++;
		}
		self::$store[$this->id] = $this;
		return true;
	}

	public function remove($_halt_before = true) {
		unset(self::$store[$this->id]);
	}

	public function getId() {
		return $this->id;
	}

	public function getClass() {
		return $this->class;
	}

	public function setClass($_class) {
		$this->class = $_class;
		return $this;
	}

	public function getFunction() {
		return $this->function;
	}

	public function setFunction($_function) {
		$this->function = $_function;
		return $this;
	}

	public function getOption() {
		return json_decode($this->option, true);
	}

	public function setOption($_option) {
		$this->option = json_encode($_option, JSON_UNESCAPED_UNICODE);
		return $this;
	}

	public function getSchedule() {
		return $this->schedule;
	}

	public function setSchedule($_schedule) {
		$this->schedule = $_schedule;
		return $this;
	}

	public function getTimeout() {
		return $this->timeout;
	}

	public function setTimeout($_timeout) {
		$this->timeout = $_timeout;
		return $this;
	}

	public function getOnce() {
		return $this->once;
	}

	public function setOnce($_once) {
		$this->once = $_once;
		return $this;
	}

	public function getState() {
		return $this->state;
	}

	public function setState($_state) {
		$this->state = $_state;
		return $this;
	}
}
