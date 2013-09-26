<?php
require_once __DIR__ . "/../lessc.inc.php";

class ErrorHandlingTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->less = new lessc();
	}

	public function compile() {
		$source = join("\n", func_get_args());
		return $this->less->compile($source);
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage .parametric-mixin is undefined
	 */
	public function testRequiredParametersMissing() {
		$this->compile(
			'.parametric-mixin (@a, @b) { a: @a; b: @b; }',
			'.selector { .parametric-mixin(12px); }'
		);
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage .parametric-mixin is undefined
	 */
	public function testTooManyParameters() {
		$this->compile(
			'.parametric-mixin (@a, @b) { a: @a; b: @b; }',
			'.selector { .parametric-mixin(12px, 13px, 14px); }'
		);
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage unrecognised input
	 */
	public function testRequiredArgumentsMissing() {
		$this->compile('.selector { rule: e(); }');
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage variable @missing is undefined
	 */
	public function testVariableMissing() {
		$this->compile('.selector { rule: @missing; }');
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage .missing-mixin is undefined
	 */
	public function testMixinMissing() {
		$this->compile('.selector { .missing-mixin; }');
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage .flipped is undefined
	 */
	public function testGuardUnmatchedValue() {
		$this->compile(
			'.flipped(@x) when (@x =< 10) { rule: value; }',
			'.selector { .flipped(12); }'
		);
	}

	/**
	 * @expectedException        Exception
	 * @expectedExceptionMessage .colors-only is undefined
	 */
	public function testGuardUnmatchedType() {
		$this->compile(
			'.colors-only(@x) when (iscolor(@x)) { rule: value; }',
			'.selector { .colors-only("string value"); }'
		);
	}
}
