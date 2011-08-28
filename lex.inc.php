<?php

function ln() { echo implode("\t", func_get_args()).PHP_EOL; }

class parallel_lex {
	const EOF = 0;

	var $opts = '';
	var $skip = '(?:[ \r\n\t]+|#.*)';
	var $grammar = array();

	static function from_file($name) {
		$cls = get_called_class(); // get rid of this, 5.3 only
		$lex = new $cls(file_get_contents($name));
		$lex->input_file = $name;
		return $lex;
	}

	function __construct($input) {
		$this->buffer = $this->input = $input;
		$this->offset = 0;

		$this->build_regex();
	}

	function build_regex() {
		$this->lookup = array();
		$this->lookup_offset = 1;

		$i = 0;
		$patterns = array();
		foreach ($this->grammar as $name => $patt) {
			if (is_string($name)) {
				$this->lookup[$i] = $name;
			} else {
				$patt = $this->preg_quote($patt);
			}
			$patterns[] = $patt;
			$i++;
		}

		array_unshift($patterns, $this->skip);
		$this->lookup_offset += 1;

		// print_r($this->lookup);
		// print_r($patterns);

		$this->regex = '/^('.implode(')|^(', $patterns).')/'.$this->opts;
	}

	protected function preg_quote($what) {
		return preg_quote($what, '/');
	}

	function next_token() {
		while (preg_match($this->regex, $this->buffer, $m)) {
			$i = count($m) - $this->lookup_offset - 1;
			$this->advance(strlen($m[0]));

			if ($i < 0) continue; // whitespace

			if (isset($this->lookup[$i])) {
				return array($this->lookup[$i], $m[0]);
			} else {
				return array(strtolower($m[0]));
			}

		}

		if ($this->offset >= strlen($this->input)) return self::EOF;
		$this->error();
	}

	function print_token($t) {
		if ($t == self::EOF) print '$EOF';
		else {
			echo "{".$t[0].(isset($t[1]) ? ', '.$t[1] : '')."}";
		}
	}

	protected function error() {
		$sum = strlen($this->input);
		throw new Exception("[{$this->offset}, {$sum}] Unexpected input in ".$this->pos());
	}

	protected function advance($n) {
		$this->offset += $n;
		$this->buffer = substr($this->buffer, $n);
	}

	protected function pos() {
		$line = $this->offset == 0 ? 0 : substr_count($this->input, "\n", 0, $this->offset) + 1;
		$file = isset($this->input_file) ? $this->input_file : "input text";
		if (preg_match('/(.*)($|\n)/'.$this->opts, $this->buffer, $m)) {
			$context = $m[1];
		}
		return "{$file}, line {$line}".(isset($context) ? ': `'.$context.'`' : '');
	}
}

