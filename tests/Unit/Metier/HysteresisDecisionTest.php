<?php

require_once __DIR__ . '/../../Metier/bootstrap.php';

use PHPUnit\Framework\TestCase;

class HysteresisDecisionTest extends TestCase {

	private $settings;
	private $log;

	protected function setUp() {
		$this->settings = new InMemorySettings();
		$this->log = new RecordingLog();
	}

	private function decide($_temp, $_status = 'Arrêté', $_lastState = '', $_consigne = 20) {
		return (new thermostatHysteresisDecision($this->settings, $this->log, new thermostatStatusLabels(new IdentityTranslator()), new IdentityTranslator()))->decide($_temp, $_consigne, $_status, $_lastState);
	}

	/**
	 * @dataProvider decisions
	 */
	public function testDecision($_expected, $_temp, $_status, $_lastState) {
		$this->assertSame($_expected, $this->decide($_temp, $_status, $_lastState));
	}

	public function decisions() {
		return array(
			'below low threshold heats' => array('heat', 18.9, 'Arrêté', ''),
			'on low threshold waits' => array('none', 19, 'Arrêté', ''),
			'above high threshold cools' => array('cool', 21.1, 'Arrêté', ''),
			'on high threshold waits' => array('none', 21, 'Arrêté', ''),
			'inside band waits' => array('none', 20, 'Arrêté', ''),
			'heating stops above high threshold' => array('stop', 21.1, 'Chauffage', ''),
			'heating goes on inside band' => array('none', 20.5, 'Chauffage', ''),
			'cooling stops below low threshold' => array('stop', 18.9, 'Climatisation', ''),
			'no heating just after cooling' => array('none', 18.5, 'Arrêté', 'cool'),
			'heating far below after cooling' => array('heat', 17.9, 'Arrêté', 'cool'),
			'no cooling just after heating' => array('none', 21.5, 'Arrêté', 'heat'),
			'cooling far above after heating' => array('cool', 22.1, 'Arrêté', 'heat'),
		);
	}

	public function testThresholdIsConfigurable() {
		$this->settings->values['hysteresis_threshold'] = 0.5;

		$this->assertSame('heat', $this->decide(19.4));
		$this->assertSame('none', $this->decide(19.6));
	}

	public function testAllowModeFiltersDirection() {
		$this->settings->values['allow_mode'] = 'cool';
		$this->assertSame('none', $this->decide(18));
		$this->assertSame('cool', $this->decide(22));

		$this->settings->values['allow_mode'] = 'heat';
		$this->assertSame('heat', $this->decide(18));
		$this->assertSame('none', $this->decide(22));
		$this->assertSame('stop', $this->decide(22, 'Chauffage'));
	}

	public function testPositiveHysteresisUsesSetpointAsBound() {
		$this->settings->values['positiveHysteresis'] = 1;
		$this->settings->values['allow_mode'] = 'heat';
		$this->assertSame('heat', $this->decide(19.9));

		$this->settings->values['allow_mode'] = 'cool';
		$this->assertSame('cool', $this->decide(20.1));

		$this->settings->values['allow_mode'] = 'all';
		$this->assertSame('none', $this->decide(19.9));
		$this->assertSame('none', $this->decide(20.1));
	}

	public function testPositiveHysteresisIsOptIn() {
		$this->settings->values['allow_mode'] = 'heat';
		$this->assertSame('none', $this->decide(19.9));

		$this->settings->values['allow_mode'] = 'cool';
		$this->assertSame('none', $this->decide(20.1));
	}

	public function testLogsBounds() {
		$this->decide(19.5, 'Arrêté', 'heat');

		$this->assertSame(array('debug Calcul => consigne : 20 hysteresis_low : 19 hysteresis_hight : 21 temp : 19.5 état précédent : heat'), $this->log->lines);
	}
}
