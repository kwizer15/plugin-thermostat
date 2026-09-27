<?php

namespace Jeedom\Plugin\Thermostat\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase {

	public function testFacadeAloneLoadsEverySourceClassUnderItsPsr4Name() {
		$src = realpath(__DIR__ . '/../../core/src');
		$classes = array();
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
			$relative = substr($file->getPathname(), strlen($src) + 1, -strlen('.php'));
			$classes[] = 'Jeedom\\Plugin\\Thermostat\\' . str_replace('/', '\\', $relative);
		}
		$script = 'require ' . var_export(realpath(__DIR__ . '/../../core/class/thermostat.class.php'), true) . ';'
			. '$missing = array();'
			. 'foreach (' . var_export($classes, true) . ' as $c) {'
			. '	if (!class_exists($c) && !interface_exists($c)) { $missing[] = $c; }'
			. '}'
			. 'echo json_encode(array("missing" => $missing, "composer" => class_exists("Composer\\\\Autoload\\\\ClassLoader", false)));';

		$result = json_decode(shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script)), true);

		$this->assertGreaterThan(60, count($classes));
		$this->assertFalse($result['composer']);
		$this->assertSame(array(), $result['missing']);
	}
}
