<?php

class listener {

	private static $store = array();

	private static $nextId = 1;

	private $id;
	private $class = '';
	private $function = '';
	private $option = '';
	private $event = array();

	public static function reset() {
		self::$store = array();
		self::$nextId = 1;
	}

	public static function all() {
		return array_values(self::$store);
	}

	public static function byClassAndFunction($_class, $_function, $_option = '') {
		foreach (self::$store as $listener) {
			if ($listener->class != $_class || $listener->function != $_function) {
				continue;
			}
			if ($_option != '' && $listener->option !== json_encode($_option, JSON_UNESCAPED_UNICODE)) {
				continue;
			}
			return $listener;
		}
		return false;
	}

	public function save() {
		if ($this->id === null) {
			$this->id = self::$nextId++;
		}
		self::$store[$this->id] = $this;
	}

	public function remove() {
		unset(self::$store[$this->id]);
	}

	public function emptyEvent() {
		$this->event = array();
	}

	public function addEvent($_id, $_type = 'cmd') {
		$id = str_replace('#', '', $_id);
		if (!in_array('#' . $id . '#', $this->event)) {
			$this->event[] = '#' . $id . '#';
		}
	}

	public function getEvent() {
		return $this->event;
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
}
