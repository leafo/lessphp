#!/usr/bin/php -q
<?php
//
// command line utility to compile less to stdout
// leaf corcoran <leafo.net>
$VERSION = "v0.3.0";

error_reporting(E_ALL);
$path  = realpath(dirname(__FILE__)).'/';

require $path."lessc.inc.php";

$fa = "Fatal Error: ";
function err($msg) {
	fwrite(STDERR, $msg."\n");
}

if (php_sapi_name() != "cli") {
	err($fa.$argv[0]." must be run in the command line.");
	exit(1);
}
$exe = array_shift($argv); // remove filename

function process($data, $import = null) {
	global $fa;

	$l = new lessc();
	if ($import) $l->importDir = $import;
	try {
		echo $l->parse($data);
		exit(0);
	} catch (exception $ex) {
		err($fa."\n".str_repeat('=', 20)."\n".
			$ex->getMessage());
		exit(1);
	}
}

// process args
$opts = array();
foreach ($argv as $loc => $a) {
	if (preg_match("/^-([a-zA-Z]+)$/", $a, $m)) {
		$m = $m[1];
		for ($i = 0; $i < strlen($m); $i++)
			$opts[$m{$i}] = $loc;
		unset($argv[$loc]);
	}
}

function has($o, &$loc = null) {
	global $opts;
	if (!isset($opts[$o])) return false;
	$loc = $opts[$o];
	return true;
}

function hasValue($o, &$value = null) {
	global $argv;
	if (!has($o,$loc)) return false;
	if (!isset($argv[$loc+1])) return false;
	$value = $argv[$loc+1];
	return true;
}

if (has("v")) {
	exit($VERSION."\n");
}

if (has("r", $loc)) {
	if (!hasValue("r", $data)) {
		while (!feof(STDIN)) {
			$data .= fread(STDIN, 8192);
		}
	}
	return process($data);
}

if (has("w")) {
	// need two files
	if (!is_file($in = array_shift($argv)) ||
		null == $out = array_shift($argv))
	{
		err($fa.$exe." -w infile outfile");
		exit(1);
	}

	echo "Watching ".$in.
		(has("n") ? ' with notifications' : '').
		", press Ctrl + c to exit.\n";

	$cache = $in;
	$last_action = 0;
	while (1) {
		clearstatcache();

		// check if anything has changed since last fail
		$updated = false;
		if (is_array($cache)) {
			foreach ($cache['files'] as $fname=>$_) {
				if (filemtime($fname) > $last_action) {
					$updated = true;
					break;
				}
			}
		} else $updated = true;

		// try to compile it
		if ($updated) {
			$last_action = time();

			try {
				$cache = lessc::cexecute($cache);
				echo "Writing updated file: ".$out."\n";
				if (!file_put_contents($out, $cache['compiled'])) {
					err($fa."Could not write to file ".$out);
					exit(1);
				}
			} catch (exception $ex) {
				echo "\nFatal Error:\n".str_repeat('=', 20)."\n".$ex->getMessage()."\n\n";

				if (has("n")) {
					`notify-send -u critical "compile failed" "{$ex->getMessage()}"`;
				}
			}
		}

		sleep(1);
	}
	exit(0);
}

if (!$fname = array_shift($argv)) {
	echo "Usage: ".$exe." input-file [output-file]\n";
	exit(1);
}

function dumpValue($node, $depth = 0) {
	if (is_object($node)) {
		$indent = str_repeat("  ", $depth);
		$out = array();
		foreach ($node->props as $prop) {
			$out[] = $indent . dumpValue($prop, $depth + 1);
		}
		$out = implode("\n", $out);
		if (!empty($node->tags)) {
			$out = "+ ".implode(", ", $node->tags)."\n".$out;
		}
		return $out;
	} elseif (is_array($node)) {
		$type = $node[0];
		if ($type == "block")
			return dumpValue($node[1], $depth);

		$out = array();
		foreach ($node as $value) {
			$out[] = dumpValue($value, $depth);
		}
		return "{ ".implode(", ", $out)." }";
	} else {
		if (is_string($node) && preg_match("/[\s,]/", $node)) {
			return '"'.$node.'"';
		}
		return $node; // normal value
	}
}

try {
	$l = new lessc($fname);
	if (has("T") || has("X")) {
		$t = $l->parseTree();
		if (has("X"))
			$out = print_r($t, 1);
		else
			$out = dumpValue($t)."\n";
	} else {
		$out = $l->parse();
	}

	if (!$fout = array_shift($argv)) {
		echo $out;
	} else {
		file_put_contents($fout, $out);
	}

} catch (exception $ex) {
	err($fa.$ex->getMessage());
	exit(1);
}


?>
