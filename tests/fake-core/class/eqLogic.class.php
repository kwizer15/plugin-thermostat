<?php

class eqLogic {

	protected static $store = array();

	protected static $nextId = 1;

	public static $saves = array();

	public static $refreshedWidgets = array();

	protected $id;
	protected $name = '';
	protected $eqType_name = '';
	protected $isEnable = 1;
	protected $configuration;
	protected $category;
	protected $_persistedConfiguration;

	public static function reset() {
		self::$store = array();
		self::$nextId = 1;
		self::$saves = array();
		self::$refreshedWidgets = array();
	}

	public static function byId($_id) {
		return isset(self::$store[$_id]) ? self::$store[$_id] : false;
	}

	public static function byType($_eqType_name, $_onlyEnable = false) {
		$return = array();
		foreach (self::$store as $eqLogic) {
			if ($eqLogic->eqType_name == $_eqType_name && (!$_onlyEnable || $eqLogic->isEnable == 1)) {
				$return[] = $eqLogic;
			}
		}
		return $return;
	}

	public function save($_direct = false) {
		if (!$_direct && method_exists($this, 'preSave')) {
			$this->preSave();
		}
		if ($this->id === null) {
			$this->id = self::$nextId++;
		}
		self::$store[$this->id] = $this;
		$this->_persistedConfiguration = $this->configuration;
		self::$saves[] = array('id' => $this->id, 'direct' => $_direct);
		if (!$_direct && method_exists($this, 'postSave')) {
			$this->postSave();
		}
		return true;
	}

	public function refresh() {
		$this->configuration = $this->_persistedConfiguration;
	}

	public function remove() {
		if (method_exists($this, 'preRemove')) {
			$this->preRemove();
		}
		foreach ($this->getCmd() as $cmd) {
			$cmd->remove();
		}
		unset(self::$store[$this->id]);
	}

	public function refreshWidget() {
		self::$refreshedWidgets[] = $this->id;
	}

	public function emptyCacheWidget() {
	}

	public function getCmd($_type = null, $_logicalId = null, $_visible = null, $_multiple = false) {
		if ($_logicalId !== null) {
			$cmds = cmd::byEqLogicIdAndLogicalId($this->id, $_logicalId, $_multiple, $_type);
		} else {
			$cmds = cmd::byEqLogicId($this->id, $_type, $_visible);
		}
		if (is_array($cmds)) {
			foreach ($cmds as $cmd) {
				$cmd->setEqLogic($this);
			}
		} elseif (is_object($cmds)) {
			$cmds->setEqLogic($this);
		}
		return $cmds;
	}

	public function getHumanName($_tag = false, $_prettify = false) {
		return '[' . $this->name . ']';
	}

	public function getId() {
		return $this->id;
	}

	public function getName() {
		return $this->name;
	}

	public function setName($_name) {
		$this->name = $_name;
		return $this;
	}

	public function getEqType_name() {
		return $this->eqType_name;
	}

	public function setEqType_name($_eqType_name) {
		$this->eqType_name = $_eqType_name;
		return $this;
	}

	public function getIsEnable($_default = 0) {
		return $this->isEnable;
	}

	public function setIsEnable($_isEnable) {
		$this->isEnable = $_isEnable;
		return $this;
	}

	public function getConfiguration($_key = '', $_default = '') {
		return utils::getJsonAttr($this->configuration, $_key, $_default);
	}

	public function setConfiguration($_key, $_value) {
		$this->configuration = utils::setJsonAttr($this->configuration, $_key, $_value);
		return $this;
	}

	public function getCategory($_key = '', $_default = '') {
		return utils::getJsonAttr($this->category, $_key, $_default);
	}

	public function setCategory($_key, $_value) {
		$this->category = utils::setJsonAttr($this->category, $_key, $_value);
		return $this;
	}

	public function getCache($_key = '', $_default = '') {
		$cache = cache::byKey('eqLogicCacheAttr' . $this->getId())->getValue();
		return utils::getJsonAttr($cache, $_key, $_default);
	}

	public function setCache($_key, $_value = null) {
		cache::set('eqLogicCacheAttr' . $this->getId(), utils::setJsonAttr(cache::byKey('eqLogicCacheAttr' . $this->getId())->getValue(), $_key, $_value));
	}
}
