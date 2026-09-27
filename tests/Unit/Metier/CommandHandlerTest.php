<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit\Metier;

require_once __DIR__ . '/../../Metier/bootstrap.php';

use Jeedom\Plugin\Thermostat\Domain\Actuator\Actuator;
use Jeedom\Plugin\Thermostat\Domain\Command\Handler;
use Jeedom\Plugin\Thermostat\Domain\StatusLabels;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingPersistence;
use Jeedom\Plugin\Thermostat\Tests\Metier\CountingRunner;
use Jeedom\Plugin\Thermostat\Tests\Metier\IdentityTranslator;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryDisplay;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemoryMemory;
use Jeedom\Plugin\Thermostat\Tests\Metier\InMemorySettings;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingActions;
use Jeedom\Plugin\Thermostat\Tests\Metier\RecordingLog;
use PHPUnit\Framework\TestCase;

class CommandHandlerTest extends TestCase {

	private $settings;
	private $memory;
	private $persistence;
	private $display;
	private $actions;
	private $engine;

	protected function setUp() {
		$this->settings = new InMemorySettings(array('orderChange' => array(array('cmd' => '#notify#'))));
		$this->memory = new InMemoryMemory();
		$this->persistence = new CountingPersistence();
		$this->display = new InMemoryDisplay();
		$this->actions = new RecordingActions();
		$this->engine = new CountingRunner();
	}

	private function handle($_logicalId, array $_options = array(), $_name = '') {
		$actuator = new Actuator($this->settings, $this->memory, $this->persistence, $this->display, $this->actions, $this->engine, new RecordingLog(), new StatusLabels(new IdentityTranslator()), new IdentityTranslator());
		return (new Handler($this->settings, $this->memory, $this->persistence, $this->display, $actuator, $this->engine, new StatusLabels(new IdentityTranslator())))->handle($_logicalId, $_name, $_options);
	}

	public function testDeltaOrderIsStoredEvenWhenLocked() {
		$this->display->locked = true;

		$this->handle('deltaOrder', array('slider' => 1.5));

		$this->assertSame(1.5, $this->memory->values['deltaOrder']);
		$this->assertSame(0, $this->display->widgetRefreshes);
	}

	public function testLockBlocksAndRefreshesWidget() {
		$this->handle('lock');
		$this->assertTrue($this->display->locked);
		$this->assertSame(1, $this->display->widgetRefreshes);

		$this->handle('thermostat', array('slider' => 22));
		$this->handle('off');
		$this->assertSame(20, $this->display->setpoint);
		$this->assertSame(array(), $this->actions->executed);

		$this->handle('unlock');
		$this->assertFalse($this->display->locked);
		$this->assertSame(3, $this->display->widgetRefreshes);
	}

	public function testMissingLockStateBlocks() {
		$this->display->lockState = false;

		$this->handle('thermostat', array('slider' => 22));

		$this->assertSame(20, $this->display->setpoint);
		$this->assertSame(1, $this->display->widgetRefreshes);
	}

	public function testOffsetsAreSavedWhenNumeric() {
		$this->handle('offset_heat', array('slider' => '-2'));
		$this->handle('offset_cool', array('slider' => 'abc'));

		$this->assertSame('-2', $this->settings->values['offset_heat']);
		$this->assertSame(0, $this->settings->values['offset_cool']);
		$this->assertSame(1, $this->persistence->fullSaves);
	}

	public function testAllowModesSaveAndRunEngine() {
		$this->display->locked = true;
		foreach (array('cool_only' => 'cool', 'heat_only' => 'heat', 'all_allow' => 'all') as $logicalId => $mode) {
			$this->handle($logicalId);
			$this->assertSame($mode, $this->settings->values['allow_mode']);
		}

		$this->assertSame(3, $this->persistence->fullSaves);
		$this->assertSame(3, $this->engine->runs);
	}

	public function testModeActionExecutesMode() {
		$this->settings->values['existingMode'] = array(array('name' => 'Eco', 'actions' => array(array('cmd' => '#a#'))));

		$this->handle('modeAction', array(), 'Eco');

		$this->assertSame('Eco', $this->display->mode);
		$this->assertSame(1, $this->engine->runs);
	}

	public function testOffStopsThermostat() {
		$this->display->status = 'Chauffage';

		$this->handle('off');

		$this->assertSame(array('#stopper#'), $this->actions->executed);
		$this->assertSame('Off', $this->display->mode);
		$this->assertSame('Arrêté', $this->display->status);
	}

	public function testSetpointChangesModeNotifiesAndRunsEngine() {
		$this->handle('thermostat', array('slider' => '21,5'));

		$this->assertSame('21,5', $this->display->setpoint);
		$this->assertSame('Aucun', $this->display->mode);
		$this->assertSame(array('#notify# {"modeChange":true}'), $this->actions->executed);
		$this->assertSame(1, $this->engine->runs);
	}

	public function testSameSetpointDoesNotRunEngine() {
		$this->handle('thermostat', array('slider' => 20));

		$this->assertSame(0, $this->engine->runs);
		$this->assertSame(array('#notify# {"modeChange":true}'), $this->actions->executed);
	}

	public function testSetpointFromModeKeepsMode() {
		$this->display->mode = 'Eco';

		$this->handle('thermostat', array('slider' => 18, 'modeChange' => true));

		$this->assertSame('Eco', $this->display->mode);
	}

	public function testInvalidSetpointIsIgnored() {
		$this->handle('thermostat', array('slider' => 'abc'));
		$this->handle('thermostat', array());

		$this->assertSame(20, $this->display->setpoint);
		$this->assertSame(array(), $this->display->events);
	}

	public function testSuspendedStoresSetpointOnly() {
		$this->display->status = 'Suspendu';

		$this->handle('thermostat', array('slider' => 22));

		$this->assertSame(22, $this->display->setpoint);
		$this->assertSame(array(), $this->actions->executed);
		$this->assertSame(0, $this->engine->runs);
	}
}
