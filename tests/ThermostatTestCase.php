<?php

namespace Jeedom\Plugin\Thermostat\Tests;

use PHPUnit\Framework\TestCase;

abstract class ThermostatTestCase extends TestCase {

	const NOW = '2026-01-15 10:00:00';

	/** @var eqLogic */
	private $device;

	protected $heater;
	protected $stopper;
	protected $cooler;
	protected $failureAction;
	protected $actuatorFailureAction;

	protected function equippedThermostat(array $_configuration = array(), $_indoor = 19, $_outdoor = 5, $_order = 20) {
		$this->heater = $this->actuator('heat');
		$this->stopper = $this->actuator('stop');
		$this->cooler = $this->actuator('cool');
		$this->failureAction = $this->actuator('failure');
		$this->actuatorFailureAction = $this->actuator('failureActuator');
		$thermostat = $this->createThermostat(array_merge(array(
			'heating' => array($this->action($this->heater)),
			'stoping' => array($this->action($this->stopper)),
			'cooling' => array($this->action($this->cooler)),
			'failure' => array($this->action($this->failureAction)),
			'failureActuator' => array($this->action($this->actuatorFailureAction)),
		), $_configuration));
		$this->setValueOf($thermostat, 'temperature', $_indoor);
		$this->setValueOf($thermostat, 'temperature_outdoor', $_outdoor);
		$this->setValueOf($thermostat, 'order', $_order);
		return $thermostat;
	}

	/** @var array */
	private $phpErrors = array();

	/** @var array */
	private $allowedPhpErrors = array();

	protected function setUp() {
		$this->phpErrors = array();
		$this->allowedPhpErrors = array();
		set_error_handler(function ($_level, $_message, $_file, $_line) {
			$this->phpErrors[] = $_message . ' (' . basename($_file) . ':' . $_line . ')';
			return true;
		});
		\cache::reset();
		\log::reset();
		\jeedom::reset();
		\scenarioExpression::reset();
		\cron::reset();
		\listener::reset();
		\plugin::reset();
		\eqLogic::reset();
		\cmd::reset();
		\calendar_event::reset();
		\fakeTranslation::$texts = array();
		$this->setNow(self::NOW);
		$this->device = new \eqLogic();
		$this->device->setName('Device');
		$this->device->setEqType_name('virtual');
		$this->device->save();
	}

	protected function tearDown() {
		restore_error_handler();
		$unexpected = array();
		foreach ($this->phpErrors as $error) {
			if (!$this->isAllowedPhpError($error)) {
				$unexpected[] = $error;
			}
		}
		$this->assertSame(array(), $unexpected, 'Erreurs PHP inattendues');
	}

	protected function allowPhpError($_message) {
		$this->allowedPhpErrors[] = $_message;
	}

	protected function phpErrors() {
		return $this->phpErrors;
	}

	private function isAllowedPhpError($_error) {
		foreach ($this->allowedPhpErrors as $allowed) {
			if (strpos($_error, $allowed) === 0) {
				return true;
			}
		}
		return false;
	}

	protected function setNow($_datetime) {
		file_put_contents(getenv('FAKETIME_TIMESTAMP_FILE'), $_datetime);
	}

	protected function sensor($_value, $_subType = 'numeric', $_collectDate = null) {
		$cmd = new \cmd();
		$cmd->setEqLogic_id($this->device->getId());
		$cmd->setName('Sensor');
		$cmd->setType('info');
		$cmd->setSubType($_subType);
		$cmd->save();
		$this->setInfo($cmd, $_value, $_collectDate);
		return $cmd;
	}

	protected function actuator($_name = 'Actuator') {
		$cmd = new \cmd();
		$cmd->setEqLogic_id($this->device->getId());
		$cmd->setName($_name);
		$cmd->setType('action');
		$cmd->setSubType('other');
		$cmd->save();
		return $cmd;
	}

	protected function setInfo(\cmd $_cmd, $_value, $_collectDate = null) {
		$date = ($_collectDate === null) ? date('Y-m-d H:i:s') : $_collectDate;
		$_cmd->setCache(array('value' => $_cmd->formatValue($_value), 'collectDate' => $date, 'valueDate' => $date));
	}

	protected function action(\cmd $_cmd, array $_options = array()) {
		return array('cmd' => '#' . $_cmd->getId() . '#', 'options' => $_options);
	}

	/**
	 * @return thermostat
	 */
	protected function createThermostat(array $_configuration = array(), $_isEnable = 1) {
		$thermostat = new \thermostat();
		$thermostat->setName('Salon');
		$thermostat->setEqType_name('thermostat');
		$thermostat->setIsEnable($_isEnable);
		foreach ($_configuration as $key => $value) {
			$thermostat->setConfiguration($key, $value);
		}
		$thermostat->save();
		return $thermostat;
	}

	protected function cmdOf(\thermostat $_thermostat, $_logicalId) {
		return $_thermostat->getCmd(null, $_logicalId);
	}

	protected function valueOf(\thermostat $_thermostat, $_logicalId) {
		return $this->cmdOf($_thermostat, $_logicalId)->execCmd();
	}

	protected function setValueOf(\thermostat $_thermostat, $_logicalId, $_value, $_collectDate = null) {
		$this->setInfo($this->cmdOf($_thermostat, $_logicalId), $_value, $_collectDate);
	}

	protected function pullCrons() {
		return \cron::searchClassAndFunction('thermostat', 'pull');
	}

	protected function cronWithOptions(array $_options) {
		return \cron::byClassAndFunction('thermostat', 'pull', $_options);
	}

	protected function executed() {
		$names = array();
		foreach (\scenarioExpression::executedCmds() as $cmd) {
			$names[] = \cmd::byId(str_replace('#', '', $cmd))->getName();
		}
		return $names;
	}
}
