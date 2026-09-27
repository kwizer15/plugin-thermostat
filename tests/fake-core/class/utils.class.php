<?php

class utils {

	public static function setJsonAttr($_attr, $_key, $_value = null) {
		if ($_value === null && !is_array($_key)) {
			if (!is_array($_attr)) {
				$_attr = is_json($_attr, array());
			}
			unset($_attr[$_key]);
		} else {
			if (!is_array($_attr)) {
				$_attr = is_json($_attr, array());
			}
			if (is_array($_key)) {
				$_attr = array_merge($_attr, $_key);
			} else {
				$_attr[$_key] = $_value;
			}
		}
		return $_attr;
	}

	public static function getJsonAttr(&$_attr, $_key = '', $_default = '') {
		if (is_array($_attr)) {
			if ($_key == '') {
				return $_attr;
			}
		} else {
			if ($_key == '') {
				return is_json($_attr, array());
			}
			if ($_attr === '') {
				if (is_array($_key)) {
					$return = array();
					foreach ($_key as $key) {
						$return[$key] = $_default;
					}
					return $return;
				}
				return $_default;
			}
			$_attr = json_decode($_attr, true);
		}
		if (is_array($_key)) {
			$return = array();
			foreach ($_key as $key) {
				$return[$key] = (isset($_attr[$key]) && $_attr[$key] !== '') ? $_attr[$key] : $_default;
			}
			return $return;
		}
		return (isset($_attr[$_key]) && $_attr[$_key] !== '') ? $_attr[$_key] : $_default;
	}

	public static function o2a($_object) {
		if (is_array($_object)) {
			$return = array();
			foreach ($_object as $key => $value) {
				$return[$key] = self::o2a($value);
			}
			return $return;
		}
		$return = array();
		$reflection = new ReflectionObject($_object);
		foreach ($reflection->getProperties() as $property) {
			if ($property->isStatic() || strpos($property->getName(), '_') === 0) {
				continue;
			}
			$property->setAccessible(true);
			$return[$property->getName()] = $property->getValue($_object);
		}
		return $return;
	}
}
