<?php

function ln() { echo implode("\t", func_get_args()).PHP_EOL; }

class simple_lex {
	const EOF = 0;

	var $opts = '';
	var $skip = '(?:[ \r\n\t]+|#.*)';

	var $lit = array( // exmaple stuff...
		'def', 'end', 'if', 'then', 'else', 'dispatch',
		'for', 'in', 'do', 'while', 'print', 'let',
		'+', '-', '/', '*', '%',
		'>=', '<=', '>', '<',
		'(', ')', ',', '=', '{', '}',
		'[', ']', '!'
	);

	var $exp = array(
		'number' => '[0-9]+(?:\.[0-9]+)?',
		'keyword' => '[\w_$][\w\d_$]*',
		'string' => '"(?:\\\\"|[^"])*?"',
	);

	function __construct($input) {
		$this->buffer = $this->input = $input;
		$this->offset = 0;

		usort($this->lit, array($this, 'length_compare'));
		uasort($this->exp, array($this, 'length_compare'));

		// print_r($this->lit);
		// print_r($this->exp);
	}

	static function from_file($name) {
		$cls = get_called_class(); // get rid of this, only php 5.3
		$lex = new $cls(file_get_contents($name));
		$lex->input_file = $name;

		return $lex;
	}

	function next_token() {
		// eat skip
		while (preg_match('/^'.$this->skip.'/'.$this->opts, $this->buffer, $m)) {
			$this->advance(strlen($m[0]));
		}

		if ($this->offset >= strlen($this->input)) return self::EOF;

		foreach ($this->lit as $lit_token) {
			if (substr($this->buffer, 0, strlen($lit_token)) == $lit_token) {
				$this->advance(strlen($lit_token));
				return array($lit_token);
			}
		}

		foreach ($this->exp as $token_name => $exp_pat) {
			$pat = '/^'.$exp_pat.'/'.$this->opts;
			if (preg_match($pat, $this->buffer, $m)) {
				$this->advance(strlen($m[0]));
				return array($token_name, $m[0]);
			}
		}

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

	// for sorting longest to shortest
	protected function length_compare($a, $b) {
		return strlen($b) - strlen($a);
	}
}

/* named capture groups? */
class parallel_lex extends simple_lex {

	function __construct($input) {
		parent::__construct($input);

		$this->build_regex();
	}

	function next_token() {

		// responsible for buffer and offset
		while (preg_match($this->regex, $this->buffer, $m)) {
			$i = count($m) - 2;
			$this->advance(strlen($m[0]));

			if ($i == 0) continue; // whitespace

			$tok = $this->regex_index[$i];
			if ($i >= $this->capture_above)
				return array($tok, $m[0]);
			else
				return array($tok);
		}

		if ($this->offset >= strlen($this->input)) return self::EOF;

		$this->error();
	}

	function build_regex() {
		$this->regex_index = array_merge(array('_skip'), $this->lit, array_keys($this->exp));

		$patterns = array_merge(array($this->skip),
			array_map(array($this, 'preg_quote'), $this->lit),
			array_values($this->exp));

		// the capture index must be equal to this or greater to capture the value
		$this->capture_above = count($patterns) - count($this->exp);

		$this->regex = '/^('.implode(')|^(', $patterns).')/'.$this->opts;
		// ln($this->regex);
	}

	protected function preg_quote($what) {
		return preg_quote($what, '/');
	}
}

