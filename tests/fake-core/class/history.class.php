<?php

class history {

	private $datetime;
	private $value;

	public function __construct($_datetime, $_value) {
		$this->datetime = $_datetime;
		$this->value = $_value;
	}

	public function getDatetime() {
		return $this->datetime;
	}

	public function getValue() {
		return $this->value;
	}
}
