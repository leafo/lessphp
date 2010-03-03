<?php
error_reporting(E_ALL);

/**
 * Go through all files matching pattern in input directory
 * and compile them, then compare them to paired file in
 * output directory.
 */
$difftool = 'diff';
$input = array(
	'dir' => 'inputs',
	'match' => '(.*?)\.less',
);

$output = array(
	'dir' => 'outputs',
	'filename' => '%s.css',
);

require realpath(dirname(__FILE__)).'/../lessc.inc.php';
$compiler = new lessc();

$fa = 'Fatal Error: ';
if (php_sapi_name() != 'cli') { 
	exit($fa.$argv[0].' must be run in the command line.');
}

$exe = array_shift($argv); // remove filename
function flag($f) {
	if (func_num_args() > 1) {
		foreach (func_get_args() as $f) if (flag($f)) return true;
		return false;
	}
	global $argv;
	$pre = strlen($f) > 1 ? '--' : '-';
	foreach ($argv as $a) {
		if (preg_match('/^'.$pre.$f.'($|\s)/', $a)) return true;
	}
	return false;
}

if (flag('h', 'help')) {
	exit('help me');
}

if (!is_dir($input['dir']) || !is_dir($output['dir']))
	exit($fa." both input and output directories must exist\n");

$tests = array();
// todo: use glob so user can select tests by name
foreach (scandir($input['dir']) as $fname) {
	if (preg_match('/^'.$input['match'].'$/', $fname, $match)) {
		$tests[] = array(
			'in' => $input['dir'].'/'.$fname,
			'out' => $output['dir'].'/'.sprintf($output['filename'], $match[1]),
		);
	}
}

$count = count($tests);
$compiling = flag('C');
$showDiff = flag('d', 'diff');
echo ($compiling ? "Compiling" : "Running")." $count test".($count == 1 ? '' : 's').":\n";

function dump($msgs, $depth = 1) {
	if (!is_array($msgs)) $msgs = array($msgs);
	foreach ($msgs as $m) {
		echo str_repeat("\t", $depth).' - '.$m."\n";
	}
}

$i = 1;
foreach ($tests as $test) {
	printf("\t[Test %04d/%04d] %s -> %s\n", $i, $count, basename($test['in']), basename($test['out']));

	try {
		ob_start();
		$parsed = trim($compiler->parse(file_get_contents($test['in'])));
		ob_end_clean();
	} catch (exception $e) {
		dump(array(
			"Failed to compile input, reason:",
			$e->getMessage(),
			"Aborting"
		));
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
			));
			break;
		}
		$expected = trim(file_get_contents($test['out']));

		if ($expected != $parsed) {
			if ($showDiff) {
				dump("Failed:");
				$tmp = $test['out'].".tmp";
				file_put_contents($tmp, $parsed);
				system($difftool.' '.$test['out'].' '.$tmp);
				unlink($tmp);

				dump("Aborting");
				break;
			} else dump("Failed, run with -d flag to view diff");
		} else {
			dump("Passed");
		}
	}

	$i++;
}

?>
