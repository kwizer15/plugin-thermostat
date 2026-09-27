<?php

class cmd {

	private static $store = array();

	private static $nextId = 1;

	public static $events = array();

	public static $history = array();

	public static $histories = array();

	public static $statistics = array();

	protected $id;
	protected $eqLogic_id;
	protected $logicalId = '';
	protected $name = '';
	protected $type = '';
	protected $subType = '';
	protected $isVisible = 1;
	protected $isHistorized = 0;
	protected $unite = '';
	protected $generic_type = '';
	protected $value = '';
	protected $order;
	protected $template;
	protected $configuration;
	protected $display;
	protected $_collectDate = '';
	protected $_valueDate = '';
	protected $_eqLogic;

	public static function reset() {
		self::$store = array();
		self::$nextId = 1;
		self::$events = array();
		self::$history = array();
		self::$histories = array();
		self::$statistics = array();
	}

	public static function all() {
		return array_values(self::$store);
	}

	public static function byId($_id) {
		if (!is_numeric($_id)) {
			return null;
		}
		return isset(self::$store[$_id]) ? self::$store[$_id] : false;
	}

	public static function byString($_string) {
		$cmd = self::byId(str_replace('#', '', $_string));
		if (!is_object($cmd)) {
			throw new Exception('La commande n\'a pas pu être trouvée : ' . $_string);
		}
		return $cmd;
	}

	public static function byEqLogicIdAndLogicalId($_eqLogic_id, $_logicalId, $_multiple = false, $_type = null) {
		$return = array();
		foreach (self::$store as $cmd) {
			if ($cmd->eqLogic_id == $_eqLogic_id && $cmd->logicalId == $_logicalId && ($_type === null || $cmd->type == $_type)) {
				if (!$_multiple) {
					return $cmd;
				}
				$return[] = $cmd;
			}
		}
		return $_multiple ? $return : false;
	}

	public static function byEqLogicId($_eqLogic_id, $_type = null, $_visible = null) {
		$return = array();
		foreach (self::$store as $cmd) {
			if ($cmd->eqLogic_id == $_eqLogic_id && ($_type === null || $cmd->type == $_type) && ($_visible === null || $cmd->isVisible == $_visible)) {
				$return[] = $cmd;
			}
		}
		return $return;
	}

	public function save() {
		if ($this->id === null) {
			$this->id = self::$nextId++;
		}
		self::$store[$this->id] = $this;
		return true;
	}

	public function remove() {
		unset(self::$store[$this->id]);
	}

	public function execute($_options = array()) {
		return '';
	}

	public function execCmd($_options = null, $_sendNodeJsEvent = false, $_quote = false) {
		if ($this->getType() == 'info') {
			$state = $this->getCache(array('collectDate', 'valueDate', 'value'));
			$this->setCollectDate(isset($state['collectDate']) ? $state['collectDate'] : date('Y-m-d H:i:s'));
			$this->setValueDate(isset($state['valueDate']) ? $state['valueDate'] : $this->getCollectDate());
			return $state['value'];
		}
		$eqLogic = $this->getEqLogic();
		if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
			throw new Exception('Equipement désactivé - impossible d\'exécuter la commande : ' . $this->getHumanName());
		}
		return $this->formatValue($this->execute($_options), $_quote);
	}

	public function formatValue($_value, $_quote = false) {
		if (is_array($_value) || is_object($_value)) {
			return '';
		}
		if ($_value === null) {
			$_value = 0;
		}
		if (trim($_value) == '' && $_value !== false && $_value !== 0) {
			return '';
		}
		$_value = trim(trim($_value), '"');
		if ($this->getType() != 'info') {
			return $_value;
		}
		switch ($this->getSubType()) {
			case 'binary':
				$value = strtolower($_value);
				if ($value == 'on' || $value == 'high' || $value == 'true' || $value === true) {
					return 1;
				}
				if ($value == 'off' || $value == 'low' || $value == 'false' || $value === false) {
					return 0;
				}
				if ((is_numeric(intval($_value)) && intval($_value) > 1) || $_value === true || $_value == 1) {
					return 1;
				}
				return 0;
			case 'numeric':
				return floatval(str_replace(',', '.', $_value));
		}
		return $_value;
	}

	public function event($_value, $_datetime = null, $_loop = 1) {
		if ($_loop > 4 || $this->getType() != 'info') {
			return;
		}
		$eqLogic = $this->getEqLogic();
		if (!is_object($eqLogic) || $eqLogic->getIsEnable() == 0) {
			return;
		}
		$value = $this->formatValue($_value);
		if ($this->getSubType() == 'numeric' && ($value > $this->getConfiguration('maxValue', $value) || $value < $this->getConfiguration('minValue', $value))) {
			return;
		}
		$oldValue = $this->execCmd();
		$repeat = ($oldValue === $value && $oldValue !== '' && $oldValue !== null);
		$this->setCollectDate(($_datetime !== null && $_datetime !== false) ? $_datetime : date('Y-m-d H:i:s'));
		$this->setCache('collectDate', $this->getCollectDate());
		$this->setValueDate(($repeat) ? $this->getValueDate() : $this->getCollectDate());
		if ($repeat && $this->getSubType() == 'binary') {
			$repeat = false;
		}
		if (!$repeat) {
			$this->setCache(array('value' => $value, 'valueDate' => $this->getValueDate()));
		}
		self::$events[] = array('cmd' => $this->logicalId, 'value' => $value, 'repeat' => $repeat);
		$this->addHistoryValue($value, $this->getCollectDate());
	}

	public function addHistoryValue($_value, $_datetime = '') {
		self::$history[] = array('cmd' => $this->logicalId, 'value' => $_value, 'datetime' => $_datetime);
	}

	public function getHistory($_dateStart = null, $_dateEnd = null) {
		return isset(self::$histories[$this->id]) ? self::$histories[$this->id] : array();
	}

	public function getStatistique($_startTime, $_endTime) {
		return isset(self::$statistics[$this->id]) ? self::$statistics[$this->id] : array();
	}

	public function getEqLogic() {
		if (!is_object($this->_eqLogic)) {
			$this->_eqLogic = eqLogic::byId($this->eqLogic_id);
		}
		return $this->_eqLogic;
	}

	public function setEqLogic($_eqLogic) {
		$this->_eqLogic = $_eqLogic;
		return $this;
	}

	public function getHumanName($_tag = false, $_prettify = false) {
		$eqLogic = $this->getEqLogic();
		return (is_object($eqLogic) ? $eqLogic->getHumanName() : '') . '[' . $this->name . ']';
	}

	public function getCache($_key = '', $_default = '') {
		$cache = cache::byKey('cmdCacheAttr' . $this->getId())->getValue();
		return utils::getJsonAttr($cache, $_key, $_default);
	}

	public function setCache($_key, $_value = null) {
		cache::set('cmdCacheAttr' . $this->getId(), utils::setJsonAttr(cache::byKey('cmdCacheAttr' . $this->getId())->getValue(), $_key, $_value));
		return $this;
	}

	public function getConfiguration($_key = '', $_default = '') {
		return utils::getJsonAttr($this->configuration, $_key, $_default);
	}

	public function setConfiguration($_key, $_value) {
		$this->configuration = utils::setJsonAttr($this->configuration, $_key, $_value);
		return $this;
	}

	public function getDisplay($_key = '', $_default = '') {
		return utils::getJsonAttr($this->display, $_key, $_default);
	}

	public function setDisplay($_key, $_value) {
		$this->display = utils::setJsonAttr($this->display, $_key, $_value);
		return $this;
	}

	public function getTemplate($_key = '', $_default = '') {
		return utils::getJsonAttr($this->template, $_key, $_default);
	}

	public function setTemplate($_key, $_value) {
		$this->template = utils::setJsonAttr($this->template, $_key, $_value);
		return $this;
	}

	public function getCollectDate() {
		return $this->_collectDate;
	}

	public function setCollectDate($_collectDate) {
		$this->_collectDate = $_collectDate;
		return $this;
	}

	public function getValueDate() {
		return $this->_valueDate;
	}

	public function setValueDate($_valueDate) {
		$this->_valueDate = $_valueDate;
		return $this;
	}

	public function getId() {
		return $this->id;
	}

	public function getEqLogic_id() {
		return $this->eqLogic_id;
	}

	public function setEqLogic_id($_eqLogic_id) {
		$this->eqLogic_id = $_eqLogic_id;
		return $this;
	}

	public function getLogicalId() {
		return $this->logicalId;
	}

	public function setLogicalId($_logicalId) {
		$this->logicalId = $_logicalId;
		return $this;
	}

	public function getName() {
		return $this->name;
	}

	public function setName($_name) {
		$this->name = $_name;
		return $this;
	}

	public function getType() {
		return $this->type;
	}

	public function setType($_type) {
		$this->type = $_type;
		return $this;
	}

	public function getSubType() {
		return $this->subType;
	}

	public function setSubType($_subType) {
		$this->subType = $_subType;
		return $this;
	}

	public function getIsVisible() {
		return $this->isVisible;
	}

	public function setIsVisible($_isVisible) {
		$this->isVisible = $_isVisible;
		return $this;
	}

	public function getIsHistorized() {
		return $this->isHistorized;
	}

	public function setIsHistorized($_isHistorized) {
		$this->isHistorized = $_isHistorized;
		return $this;
	}

	public function getUnite() {
		return $this->unite;
	}

	public function setUnite($_unite) {
		$this->unite = $_unite;
		return $this;
	}

	public function getGeneric_type() {
		return $this->generic_type;
	}

	public function setGeneric_type($_generic_type) {
		$this->generic_type = $_generic_type;
		return $this;
	}

	public function getValue() {
		return $this->value;
	}

	public function setValue($_value) {
		$this->value = $_value;
		return $this;
	}

	public function getOrder() {
		return $this->order;
	}

	public function setOrder($_order) {
		$this->order = $_order;
		return $this;
	}
}
