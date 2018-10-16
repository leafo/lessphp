<?php

class ServerTest extends \PHPUnit_Framework_TestCase
{
	public function testCheckedCachedCompile()
	{
		$server = new lessc();
		$server->setImportDir(__DIR__ . '/inputs/test-imports/');
		$css = $server->checkedCachedCompile(__DIR__ . '/inputs/import.less', '/tmp/less.css');

		$this->assertFileExists('/tmp/less.css');
		$this->assertFileExists('/tmp/less.css.meta');
		$this->assertEquals($css, file_get_contents('/tmp/less.css'));
		$this->assertNotNull(unserialize(file_get_contents('/tmp/less.css.meta')));
	}
}
