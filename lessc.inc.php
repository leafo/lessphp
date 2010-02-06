<?php

/**
 * less.inc.php
 * v0.2.0
 *
 * less css compiler
 * adapted from http://lesscss.org/docs.html
 *
 * leaf corcoran <leafo.net>
 */


// future todo: define type names as constants

// todo: potential problem with parse tree search order:
//
// #default['color'] is an accessor, but if color is searched
// first then #def is matched as a color and it returns true and the head is
// moved to ault['color']; That is then seen as a second value in the list
//
// solution, enforce at least a space, } or {, or ; after match
// i can consume the spaces but not the symbols
// need more time to think about this, maybe leaving order requirement is good
//

class lessc {
	private $buffer;
	private $count;
	private $depth;
	private $line;
	private $expandStack;

	private $tests = array();

	private $env = array();

	static private $precedence = array(
		'+' => '0',
		'-' => '0',
		'*' => '1',
		'/' => '1',
	);
	static private $operatorString; // regex string to any of the operators

	static private $dtypes = array('expression', 'variable'); // types with delayed compilation
	static private $units = array(
		'px', '%', 'in', 'cm', 'mm', 'em', 'ex', 'pt', 'pc', 's');


	// compile chunk off the head of buffer
	function chunk() {
		if (empty($this->buffer)) return false;

		// a property
		// [keyword] : [propertyValue] ;
		$s = $this->seek();
		if ($this->keyword($key) && $this->literal(':') && $this->propertyValue($value) && $this->end()) {
			$this->append($key, $value);
			return "$key: ".$this->compileValue($value).";\n";
		} else {
			/*
			if ($this->peek("(.*?)\n", $m))
				echo "failed at `".$m[1]."`\n";
			*/
			$this->seek($s);
		}

		// function block

		// regular block

		// close block
		// import statement


		// setting variable
		$s = $this->seek();
		if ($this->variable($name) && $this->literal(':') && $this->propertyValue($value) && $this->end()) {
			$this->append('@'.$name, $value);
			return true;
		} else {
			$this->seek($s);
		}

		// mixin import

		// spare ;

		return false;	
	}

	// a list of expressions
	function expressionList(&$exps) {
		$values = array();	

		while ($this->expression($exp)) {
			$values[] = $exp;
		}
		
		if (count($values) == 0) return false;

		$exps = $this->compressList($values, ' ');
		return true;
	}

	function expression(&$out) {
		$s = $this->seek();
		if ($this->literal('(') && $this->expression($exp) && $this->literal(')')) {
			$lhs = $exp;
		} elseif ($this->seek($s) && $this->value($val)) {
			$lhs = $val;
		} else {
			return false;
		}

		$out = $this->expHelper($lhs, 0);
		return true;
	}

	// resursively parse infix equation with $lhs at precedence $minP
	function expHelper($lhs, $minP) {
		$ss = $this->seek();
		// try to find a valid operator
		while ($this->match(self::$operatorString.'\s+', $m) && self::$precedence[$m[1]] >= $minP) {
			// get rhs
			$s = $this->seek();
			if ($this->literal('(') && $this->expression($exp) && $this->literal(')')) {
				$rhs = $exp;
			} elseif ($this->seek($s) && $this->value($val)) {
				$rhs = $val;
			} else break;

			// peek for next operator to see what to do with rhs
			if ($this->peek(self::$operatorString, $next) && self::$precedence[$next[1]] > $minP) {
				$rhs = $this->expHelper($rhs, self::$precedence[$next[1]]);
			}

			// don't evaluate yet if it is dynamic
			if (in_array($rhs[0], self::$dtypes) || in_array($lhs[0], self::$dtypes))
				$lhs = array('expression', $m[1], $lhs, $rhs);
			else
				$lhs = $this->evaluate($m[1], $lhs, $rhs);

			$ss = $this->seek();
		}
		$this->seek($ss);

		return $lhs;
	}

	// consume a list of values for a property
	function propertyValue(&$value) {
		$values = array();	
		
		$s = null;
		while ($this->expressionList($v)) {
			$values[] = $v;
			$s = $this->seek();
			if (!$this->literal(',')) break;
		}

		if ($s) $this->seek($s);

		if (count($values) == 0) return false;

		$value = $this->compressList($values, ', ');
		return true;
	}

	function value(&$value) {
		// try a unit
		if ($this->unit($value)) return true;	

		// accessor 
		// color
		if ($this->color($value)) return true;

		// css function
		// string

		// try a keyword
		if ($this->keyword($word)) {
			$value = array('keyword', $word);
			return true;
		}

		// try a variable
		if ($this->variable($vname)) {
			$value = array('variable', '@'.$vname);
			return true;
		}

		return false;
	}

	function unit(&$unit, $allowed = null) {
		if (!$allowed) $allowed = self::$units;

		if ($this->match('(-?[0-9]*(\.)?[0-9]+)('.implode('|', $allowed).')?', $m)) {
			if (!isset($m[3])) $m[3] = 'number';
			$unit = array($m[3], $m[1]);
			return true;
		}

		return false;
	}

	function color(&$out) {
		$color = array('color');

		if ($this->match('(#([0-9a-f]{6})|#([0-9a-f]{3}))', $m)) {
			if (isset($m[3])) {
				$num = $m[3];
				$width = 16;
			} else {
				$num = $m[2];
				$width = 256;
			}

			$num = hexdec($num);
			foreach(array(3,2,1) as $i) {
				$t = $num % $width;
				$num /= $width;

				$color[$i] = $t * (256/$width) + $t * floor(16/$width);
			}
			
			$out = $color;
			return true;
		} else {
			// try for rgb(a) function	
			$s = $this->seek();
			if (!$this->literal('rgb')) return false;

			if ($this->literal('a')) $count = 4;
			else $count = 3;

			if (!$this->literal('(')) {
				$this->seek($s);
				return false;
			}

			foreach (range(1, $count) as $i) {
				
			}

			if (!$this->literal('(')) {
				$this->seek($s);
				return false;
			}
		
			$out = $color;
			return true;
		}

		return false;
	}

	function variable(&$name) {
		$s = $this->seek();
		if ($this->literal('@', false) && $this->keyword($name)) {
			return true;	
		}
		return false;
	}

	function keyword(&$word) {
		if ($this->match('([\w_\-!"][\w\-_"]*)', $m)) {
			$word = $m[1];
			return true;
		}
		return false;
	}

	function end() {
		return $this->literal(';');
	}

	function compressList($items, $delim) {
		if (count($items) == 1) return $items[0];	
		else return array('list', $delim, $items);
	}

	function compileValue($value) {
		switch($value[0]) {
		case 'list':
			return implode($value[1], array_map(array($this, 'compileValue'), $value[2]));
		case 'keyword':
		case 'number':
			return $value[1];
		case 'expression':
			return $this->compileValue($this->evaluate($value[1], $value[2], $value[3]));
			break;
		case 'variable':
			$tmp = $this->compileValue(
				$this->getVal($value[1], $this->pushName($value[1]))
			);
			$this->popName();

			return $tmp;
		default: // assumed to be unit	
			return $value[1].$value[0];
		}
	}

	// evaluate an expression
	function evaluate($op, $left, $right) {
		// evaluate any expressions or variables on the left and right
		$pushed = 0; // number of names pushed

		while (in_array($left[0], self::$dtypes)) {
			if($left[0] == 'expression')
				$left = $this->evaluate($left[1], $left[2], $left[3]);
			else if ($left[0] == 'variable') {
				$left = $this->getVal($left[1], $this->pushName($left[1]), array('number', 0));
				$pushed++;
			}
		}
		while ($pushed != 0) { $this->popName(); $pushed--; }

		while (in_array($right[0], self::$dtypes)) {
			if($right[0] == 'expression')
				$right = $this->evaluate($right[1], $right[2], $right[3]);
			else if ($right[0] == 'variable') {
				$right = $this->getVal($right[1], $this->pushName($right[1]), array('number', 0));
				$pushed++;
			}
		}
		while ($pushed != 0) { $this->popName(); $pushed--; }

		if ($left [0] == 'color' && $right[0] == 'color') {
			return $this->op_color_color($op, $left, $right);
		}

		if ($left[0] == 'color') {
			return $this->op_color_number($op, $left, $right);
		}

		if ($right[0] == 'color') {
			return $this->op_number_color($op, $left, $right);
		}

		if ($left[0] == 'keyword' || $right[0] == 'keyword' ||
			$left[0] == 'string' || $right[0] == 'string')
		{
			// look for negative op
			if ($op == '-') $right[1] = '-'.$right[1];
			return array('keyword', $this->compileValue($left) .' '. $this->compileValue($right));
		}
	
		// default to number operation
		return $this->op_number_number($op, $left, $right);
	}

	// make sure a color's components don't go out of bounds
	private function fixColor($c) {
		for ($i = 1; $i < 4; $i++) {
			if ($c[$i] < 0) $c[$i] = 0;
			if ($c[$i] > 255) $c[$i] = 255;
			$c[$i] = floor($c[$i]);
		}
		return $c;
	}

	private function op_number_color($op, $lft, $rgt) {
		if ($op == '+' || $op = '*') {
			return $this->op_color_number($op, $rgt, $lft);
		}
	}

	private function op_color_number($op, $lft, $rgt) {
		if ($rgt[0] == '%') $rgt[1] /= 100;

		return $this->op_color_color($op, $lft,
			array('color', $rgt[1], $rgt[1], $rgt[1]));
	}

	private function op_color_color($op, $lft, $rgt) {
		$newc = array('color');

		switch ($op) {
		case '+':
			$newc[] = $lft[1] + $rgt[1];
			$newc[] = $lft[2] + $rgt[2];
			$newc[] = $lft[3] + $rgt[3];
			break;
		case '*':
			$newc[] = $lft[1] * $rgt[1];
			$newc[] = $lft[2] * $rgt[2];
			$newc[] = $lft[3] * $rgt[3];
			break;
		case '-':
			$newc[] = $lft[1] - $rgt[1];
			$newc[] = $lft[2] - $rgt[2];
			$newc[] = $lft[3] - $rgt[3];
			break;
		case '/';
			if ($rgt[1] == 0 || $rgt[2] == 0 || $rgt[3] == 0)
				throw new exception("parse error: can't divide by zero");
			$newc[] = $lft[1] / $rgt[1];
			$newc[] = $lft[2] / $rgt[2];
			$newc[] = $lft[3] / $rgt[3];
			break;
		default:
			throw new exception('parse error: color op number failed on op '.$op);
		}
		return $this->fixColor($newc);
	}

	// operator on two numbers
	function op_number_number($op, $left, $right) {
		if ($right[0] == '%') $right[1] /= 100;

		// figure out type
		if ($right[0] == 'number' || $right[0] == '%') $type = $left[0];
		else $type = $right[0];

		$value = 0;
		switch($op) {
		case '+':
			$value = $left[1] + $right[1];
			break;	
		case '*':
			$value = $left[1] * $right[1];
			break;	
		case '-':
			$value = $left[1] - $right[1];
			break;	
		case '/':
			if ($right[1] == 0) throw new exception('parse error: divide by zero');
			$value = $left[1] / $right[1];
			break;
		default:
			throw new exception('parse error: unknown number operator: '.$op);	
		}

		return array($type, $value);
	}


	/* environment functions */

	// push name on expand stack, and return its 
	// count before being pushed
	function pushName($name) {
		$count = array_count_values($this->expandStack);
		$count = isset($count[$name]) ? $count[$name] : 0;

		$this->expandStack[] = $name;

		return $count;
	}

	// pop name off expand stack and return it
	function popName() {
		return array_pop($this->expandStack);
	}

	// push a new environment
	private function push() {
		$this->level++;
		$this->env[] = array();
	}

	// pop environment off the stack
	private function pop() {
		if ($this->level == 1)
			throw new exception('parse error: unexpected end of block');

		$this->level--;
		return array_pop($this->env);
	}

	// set something in the current env
	private function set($name, $value) {
		$this->env[count($this->env) - 1][$name] = $value;
	}

	// append to array in the current env
	private function append($name, $value) {
		$this->env[count($this->env) - 1][$name][] = $value;
	}

	// put on the front of the value
	private function prepend($name, $value) {
		if (isset($this->env[count($this->env) - 1][$name]))
			array_unshift($this->env[count($this->env) - 1][$name], $value);
		else $this->append($name, $value);
	}

	// get the highest occurrence of value
	private function get($name, $env = null) {
		if (empty($env)) $env = $this->env;

		for ($i = count($env) - 1; $i >= 0; $i--)
			if (isset($env[$i][$name])) return $env[$i][$name];

		return null;
	}

	// get the most recent value of a variable
	// return default if it isn't found
	// $skip is number of vars to skip
	private function getVal($name, $skip = 0, $default = array('keyword', '')) {
		$val = $this->get($name);
		if ($val == null) return $default;

		$tmp = $this->env;
		while (!isset($tmp[count($tmp) - 1][$name])) array_pop($tmp);
		while ($skip > 0) {
			$skip--;

			if (!empty($val)) {
				array_pop($val);
			}

			if (empty($val)) {
				array_pop($tmp);
				$val = $this->get($name, $tmp);
			}

			if (empty($val)) return $default;
		}

		return end($val);
	}

	// merge a block into the current env
	private function merge($name, $value) {
		// if the current block isn't there then just set
		$top =& $this->env[count($this->env) - 1];
		if (!isset($top[$name])) return $this->set($name, $value);

		// copy the block into the old one, including meta data
		foreach ($value as $k=>$v) {
			// todo: merge property values instead of replacing
			// have to check type for this
			$top[$name][$k] = $v;
		}
	}


	function literal($what, $eatWhitespace = true) {
		// shortcut on single letter
		if (!$eatWhitespace and strlen($what) == 1) {
			if ($this->buffer{$this->count} == $what) {
				$this->count++;
				return true;
			}
			else return false;
		}

		return $this->match($this->preg_quote($what), $m, $eatWhitespace);
	}

	private function preg_quote($what) {
		return preg_quote($what, '/');
	}
	
	// try to match something on head of buffer
	function match($regex, &$out, $eatWhitespace = true) {
		$r = '/'.$regex.($eatWhitespace ? '\s*' : '').'/Ais';
		if (preg_match($r, $this->buffer, $out, null, $this->count)) {
			$this->count += strlen($out[0]);
			$this->tests[] = 'PASS: '.$r;
			return true;
		}
		$this->tests[] = 'FAIL: '.$r;
		return false;
	}


	// match something without consuming it
	function peek($regex, &$out = null) {
		$r = '/'.$regex.'/Ais';
		$result =  preg_match($r, $this->buffer, $out, null, $this->count);
		$this->tests[] = 'PEEK '.$result.': '.$r;
		
		return $result;
	}


	// seek to a spot in the buffer or return where we are on no argument
	function seek($where = null) {
		if (!$where) return $this->count;
		else $this->count = $where;
		return true;
	}

	// parse and compile buffer
	function parse($str = null) {
		if ($str) $this->buffer = $str;		

		$this->env = array();
		$this->expandStack = array();
		$this->depth = $this->count = 0;
		$this->line = 1;

		$this->buffer = $this->removeComments($this->buffer);
		$this->push(); // set up global scope
		$this->set('__tags', array('')); // equivalent to 1 in tag multiplication

		// trim whitespace on head
		if (preg_match('/^\s+/', $this->buffer, $m)) {
			$this->line  += substr_count($m[0], "\n");
			$this->buffer = ltrim($this->buffer);
		}

		$out = '';
		while (false !== ($compiled = $this->chunk())) {
			if (is_string($compiled)) $out .= $compiled;
		}

		if ($this->count != strlen($this->buffer))  {
			if ($this->peek("(.*?)\n", $m))
				throw new exception('parse error: failed at `'.$m[1].'`');
		}

		if (count($this->env) > 1)
			throw new exception('failed to parse: unclosed block');


		print_r($this->env);
		// print_r($this->tests);
		return $out;
	}


	function __construct($fname = null) {
		if (!self::$operatorString) {
			self::$operatorString = 
				'('.implode('|', array_map(array($this, 'preg_quote'), array_keys(self::$precedence))).')';
		}

		if ($fname) {
			if (!is_file($fname)) {
				throw new Exception('load error: failed to find '.$fname);
			}
			$pi = pathinfo($fname);

			$this->fileName = $fname;
			$this->importDir = $pi['dirname'].'/';
			$this->buffer = file_get_contents($fname);
		}
	}

	// remove comments from $text
	// todo: make it work for all functions, not just url
	function removeComments($text) {
		$out = '';

		while (!empty($text) &&
			preg_match('/^(.*?)("|\'|\/\/|\/\*|url\(|$)/is', $text, $m))
		{
			if (!trim($text)) break;

			$out .= $m[1];
			$text = substr($text, strlen($m[0]));

			switch ($m[2]) {
			case 'url(':
				preg_match('/^(.*?)(\)|$)/is', $text, $inner);
				$text = substr($text, strlen($inner[0]));
				$out .= $m[2].$inner[1].$inner[2];
				break;
			case '//':
				preg_match("/^(.*?)(\n|$)/is", $text, $inner);
				// give back the newline
				$text = substr($text, strlen($inner[0]) - 1);
				break;
			case '/*';
				preg_match("/^(.*?)(\*\/|$)/is", $text, $inner);
				$text = substr($text, strlen($inner[0]));
				break;
			case '"':
			case "'":
				preg_match("/^(.*?)(".$m[2]."|$)/is", $text, $inner);
				$text = substr($text, strlen($inner[0]));
				$out .= $m[2].$inner[1].$inner[2];
				break;
			}
		}

		$this->count = 0;
		return $out;
	}

}



?>
