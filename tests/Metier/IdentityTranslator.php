<?php

class IdentityTranslator implements thermostatTranslator {

	public function translate($_text) {
		return preg_replace('/\{\{(.*?)\}\}/s', '$1', $_text);
	}
}
