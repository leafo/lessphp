<?php
error_reporting(E_ALL);

require realpath(dirname(__FILE__)).'/../lessc.inc.php';

// sorts the selectors in stylesheet in order to normalize it for comparison

$exe = array_shift($argv); // remove filename

if (!$fname = array_shift($argv)) {
	$fname = "php://stdin";
}

// also sorts the tags in the block
function sort_key($block) {
	if (!isset($block->sort_key)) {
		sort($block->tags, SORT_STRING);
		$block->sort_key = implode(",", $block->tags);
	}

	return $block->sort_key;
}

class sort_css extends lessc {
	function __construct() {
		parent::__construct();
	}

	// normalize numbers
	function compileValue($value) {
		$ignore = array('list', 'keyword', 'string', 'color', 'function');
		if ($value[0] == 'number' || !in_array($value[0], $ignore)) {
			$value[1] = $value[1] + 0; // convert to either double or int
		}

		return parent::compileValue($value);
	}

	function parse_and_sort($str) {
		$root = $this->parseTree($str);

		$less = $this;
		usort($root->props, function($a, $b) use ($less) {

			$sort = strcmp(sort_key($a[1]), sort_key($b[1]));
			if ($sort == 0)
				return strcmp($less->compileBlock($a[1]), $less->compileBlock($b[1]));
			return $sort;
		});

		return $this->compileBlock($root);
	}
}

$sorter = new sort_css;
echo $sorter->parse_and_sort(file_get_contents($fname));

