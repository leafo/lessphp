<?php

require_once __DIR__ . "/../lessc.inc.php";

// Runs all the tests in inputs/ and compares their output to ouputs/

function _dump($value) {
	fwrite(STDOUT, print_r($value, true));
}

function _quote($str) {
	return preg_quote($str, "/");
}

class InputTest extends PHPUnit_Framework_TestCase {
	protected static $importDirs = array("inputs/test-imports");

	protected static $testDirs = array(
		"inputs" => "outputs",
		"inputs_lessjs" => "outputs_lessjs",
	);

	public function setUp() {
		$this->less = new lessc();
		$this->less->importDir = array_map(function($path) {
			return __DIR__ . "/" . $path;
		}, self::$importDirs);
	}

	/**
	 * @dataProvider fileNameProvider
	 */
	public function testInputFile($inFname) {
		if ($pattern = getenv("BUILD")) {
			return $this->buildInput($inFname);
		}

		$outFname = self::outputNameFor($inFname);

		if (!is_readable($outFname)) {
			$this->fail("$outFname is missing, ".
				"consider building tests with BUILD=true");
		}

		$input = file_get_contents($inFname);
		$output = file_get_contents($outFname);

		$this->assertEquals($output, $this->less->parse($input));
	}

	public function fileNameProvider() {
		return array_map(function($a) { return array($a); },
			self::findInputNames());
	}

	// only run when env is set
	public function buildInput($inFname) {
		$css = $this->less->parse(file_get_contents($inFname));
		file_put_contents(self::outputNameFor($inFname), $css);
	}

	static public function findInputNames($pattern="*.less") {
		$files = array();
		foreach (self::$testDirs as $inputDir => $outputDir) {
			$files = array_merge($files, glob(__DIR__ . "/" . $inputDir . "/" . $pattern));
		}

		return array_filter($files, "is_file");
	}

	static public function outputNameFor($input) {
		$front = _quote(__DIR__ . "/");
		$out = preg_replace("/^$front/", "", $input);

		foreach (self::$testDirs as $inputDir => $outputDir) {
			$in = _quote($inputDir . "/");
			$rewritten = preg_replace("/$in/", $outputDir . "/", $out);
			if ($rewritten != $out) {
				$out = $rewritten;
				break;
			}
		}

		$out = preg_replace("/.less$/", ".css", $out);

		return __DIR__ . "/" . $out;
	}
}

