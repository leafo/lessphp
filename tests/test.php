#!/usr/bin/env php
<?php
error_reporting(E_ALL);

/**
 * Go through all files matching pattern in input directory
 * and compile them, then compare them to paired file in
 * output directory.
 */
$difftool = 'diff -b -B -t -u';
$input = array(
	'dir' => 'inputs',
	'glob' => '*.less',
);

$output = array(
	'dir' => 'outputs',
	'filename' => '%s.css',
);


$prefix = strtr(realpath(dirname(__FILE__)), '\\', '/');
require $prefix.'/../lessc.inc.php';

$compiler = new lessc();
$compiler->importDir = array($input['dir'].'/test-imports');

$fa = 'Fatal Error: ';
if (php_sapi_name() != 'cli') {
	exit($fa.$argv[0].' must be run in the command line.');
}

$opts = getopt('hCd::g');

if ($opts === false || isset($opts['h'])) {
	echo <<<EOT
Usage: ./test.php [options] [searchstring]

where [options] can be a mix of these:

  -h              Show this help message and exit.

  -d=[difftool]   Show the diff of the actual output vs. the reference when a
                  test fails; uses 'diff -b -B -t -u' by default.

                  The test is aborted after the first failure report, unless
                  you also specify the '-g' option ('go on').

  -g              Continue executing the other tests when a test fails and
                  option '-d' is active.

  -C              Regenerate ('compile') the reference output files from the
                  given inputs.

                  WARNING: ONLY USE THIS OPTION WHEN YOU HAVE ASCERTAINED
                           THAT lessphp PROCESSES ALL TESTS CORRECTLY!

The optional [searchstring] is used to filter the input files: only tests
which have filename(s) containing the specified searchstring will be
executed. I.e. the corresponding glob pattern is '*[searchstring]*.less'.

The script EXIT CODE is the number of failed tests (with a maximum of 255),
0 on success and 1 when this help message is shown. This aids in integrating
this script in larger (user defined) shell test scripts.


Examples of use:

- Test the full test set:
    ./test.php

- Run only the mixin tests:
    ./test.php mixin

- Use a custom diff tool to show diffs for failing tests
    ./test.php -d=meld

EOT;
	exit(1);
}

$input['dir'] = $prefix.'/'.$input['dir'];
$output['dir'] = $prefix.'/'.$output['dir'];
if (!is_dir($input['dir']) || !is_dir($output['dir']))
	exit($fa." both input and output directories must exist\n");

$exe = array_shift($argv); // remove filename
// get the first non flag as search string
$searchString = null;
foreach ($argv as $a) {
	if (strlen($a) > 0 && $a{0} != '-') {
		$searchString = $a;
		break;
	}
}

$tests = array();
$matches = glob($input['dir'].'/'.(!is_null($searchString) ? '*'.$searchString : '' ).$input['glob']);
if ($matches) {
	foreach ($matches as $fname) {
		extract(pathinfo($fname)); // for $filename, from php 5.2
		$tests[] = array(
			'in' => $fname,
			'out' => $output['dir'].'/'.sprintf($output['filename'], $filename),
		);
	}
}

$count = count($tests);
$compiling = isset($opts["C"]);
$continue_when_test_fails = isset($opts["g"]);
$showDiff = isset($opts["d"]);
if ($showDiff && !empty($opts["d"])) {
	$difftool = $opts["d"];
}

echo ($compiling ? "Compiling" : "Running")." $count test".($count == 1 ? '' : 's').":\n";

function dump($msgs, $depth = 1, $prefix="    ") {
	if (!is_array($msgs)) $msgs = array($msgs);
	foreach ($msgs as $m) {
		echo str_repeat($prefix, $depth).' - '.$m."\n";
	}
}

$fail_prefix = " ** ";

$fail_count = 0;
$i = 1;
foreach ($tests as $test) {
	printf("    [Test %04d/%04d] %s -> %s\n", $i, $count, basename($test['in']), basename($test['out']));

	try {
		ob_start();
		$parsed = trim($compiler->parse(file_get_contents($test['in'])));
		ob_end_clean();
	} catch (exception $e) {
		dump(array(
			"Failed to compile input, reason:",
			$e->getMessage(),
			"Aborting"
		), 1, $fail_prefix);
		break;
	}

	if ($compiling) {
		file_put_contents($test['out'], $parsed);
	} else {
		if (!is_file($test['out'])) {
			dump(array(
				"Failed to find output file: $test[out]",
				"Maybe you forgot to compile tests?",
				"Aborting"
			), 1, $fail_prefix);
			break;
		}
		$expected = trim(file_get_contents($test['out']));

		// don't care about CRLF vs LF change (DOS/Win vs. UNIX):
		$expected = trim(str_replace("\r\n", "\n", $expected));
		$parsed = trim(str_replace("\r\n", "\n", $parsed));

		if ($expected != $parsed) {
			$fail_count++;
			if ($showDiff) {
				dump("Failed:", 1, $fail_prefix);
				$tmp = $test['out'].".tmp";
				file_put_contents($tmp, $parsed);
				system($difftool.' '.$test['out'].' '.$tmp);
				unlink($tmp);

				if (!$continue_when_test_fails) {
					dump("Aborting");
					break;
				} else {
					echo "===========================================================================\n";
				}
			} else {
				dump("Failed, run with -d flag to view diff", 1, $fail_prefix);
			}
		} else {
			dump("Passed");
		}
	}

	$i++;
}

exit($fail_count > 255 ? 255 : $fail_count);
?>
