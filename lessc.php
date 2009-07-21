<?php
//
// command line utility to compile less to stdout
//
// leaf corcoran <leafo.net>

require './lessc.inc.php';

array_shift($argv);
$argv[] = 'test.css'; // default file

if (false !== ($loc = array_search("-r", $argv))) {
	unset($argv[$loc]);
	$c = array_shift($argv);
} else {
	if (!is_file($fname = array_shift($argv)))
		exit('failed to find file: '.$fname);
	$c = file_get_contents($fname);
}

$l = new lessc();
try {
	echo $l->parse($c);
} catch (exception $ex) {
	echo "Critical Error:\n".str_repeat('=', 20)."\n".$ex->getMessage()."\n";
}

?>
