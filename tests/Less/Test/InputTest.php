<?php

require_once dirname(__FILE__) . '/../../../autoload.php';


// Runs all the tests in inputs/ and compares their output to ouputs/
function _dump($value)
{
	fwrite(STDOUT, print_r($value, true));
}

function _quote($str)
{
	return preg_quote($str, "/");
}

class Less_Test_InputTest extends PHPUnit_Framework_TestCase
{
	protected static $inputDir = '../Resources/inputs';
	protected static $outputDir = '../Resources/outputs';

	/**
	 * @var Less_Compiler
	 */
	protected $less;

	public function setUp()
	{
		$this->less = new Less_Compiler();
		$this->less->importDir = array(__DIR__ . "/" . self::$inputDir . "/test-imports");
	}

	/**
	 * @dataProvider fileNameProvider
	 */
	public function testInputFile($inFname)
	{
		if ($pattern = getenv("BUILD")) {
			return $this->buildInput($inFname);
		}

		$outFname = self::outputNameFor($inFname);

		if (! is_readable($outFname)) {
			$this->fail("$outFname is missing, " . "consider building tests with BUILD=true");
		}

		$input = file_get_contents($inFname);
		$output = file_get_contents($outFname);

		$this->assertEquals($output, $this->less->parse($input));
	}

	public function buildInput($inFname)
	{
		$css = $this->less->parse(file_get_contents($inFname));
		return file_put_contents(self::outputNameFor($inFname), $css);
	}

	// only run when env is set

	static public function outputNameFor($input)
	{
		$front = _quote(__DIR__ . "/");
		$out = preg_replace("/^$front/", "", $input);

		$in = _quote(self::$inputDir . "/");
		$out = preg_replace("/$in/", self::$outputDir . "/", $out);
		$out = preg_replace("/.less$/", ".css", $out);

		return __DIR__ . "/" . $out;
	}

	public function fileNameProvider()
	{
		return array_map(function ($a) {
				return array($a);
			}, self::findInputNames());
	}

	static public function findInputNames($pattern = "*.less")
	{
		$files = glob(__DIR__ . "/" . self::$inputDir . "/" . $pattern);

		return array_filter($files, "is_file");
	}
}
