<?php

require_once __DIR__ . "/../lessc.inc.php";

class ApiTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->less = new lessc();
	}

	public function testOldInterface() {
		$this->less = new lessc(__DIR__ . "/inputs/hi.less");
		$out = $this->less->parse(array("hello" => "10px"));
		$this->assertEquals(trim($out), 'div:before { content:"hi!"; }');
	}

	public function testInjectVars() {
		$out = $this->less->parse(".magic { color: @color;  width: @base - 200; }",
			array(
				'color' => 'red',
				'base' => '960px'
			));
	
		$this->assertEquals(trim($out), trim("
.magic {
  color:red;
  width:760px;
}"));

	}

	public function testDisableImport() {
		$this->less->importDisabled = true;
		$this->assertEquals(
			$this->compile("@import 'hello';"),
			"/* import disabled */");
	}

	public function testUserFunction() {
		$this->less->registerFunction("add-two", function($list) {
			list($a, $b) = $list[2];
			return $a[1] + $b[1];
		});

		$this->assertEquals(
			$this->compile("result: add-two(10, 20);"),
			"result:30;");
		
		return $this->less;
	}

	/**
	 * @depends testUserFunction
	 */
	public function testUnregisterFunction($less) {
		$less->unregisterFunction("add-two");

		$this->assertEquals(
			$this->compile("result: add-two(10, 20);"),
			"result:add-two(10,20);");
	}



	public function testFormatters() {
		$src = "
			div, pre {
				color: blue;
				span, .big, hello.world {
					height: 20px;
					color:#ffffff + #000;
				}
			}";

		$this->less->setFormatter("compressed");
		$this->assertEquals(
			$this->compile($src), "div,pre{color:blue;}div span,div .big,div hello.world,pre span,pre .big,pre hello.world{height:20px;color:#fff;}");

		// TODO: fix the output order of tags
		$this->less->setFormatter("lessjs");
		$this->assertEquals(
			$this->compile($src),
"div, 
pre {
  color: blue;
}
div span, 
div .big, 
div hello.world, 
pre span, 
pre .big, 
pre hello.world {
  height: 20px;
  color: #ffffff;
}");

	}

	public function compile($str) {
		return trim($this->less->parse($str));
	}

}
