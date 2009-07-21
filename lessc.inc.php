<?php

/**
 * less.inc.php
 *
 * less css compiler 
 * adapted from http://lesscss.org/docs.html
 *
 * leaf corcoran <leafo.net>
 */


// design todo:
//
// right now I use advance to advance the buffer, but i also use a temporary
// counter to move up when exploring a parse tree.
//
// it works for what I have, but if I have branching parse trees then I think it
// would be useful to have an counter stack. (forget and remember position)
//
// then I could get rid of advance completely, but it might be slow using the 
// regex to ignore X characters on every match
//
// ** also I can then have it so count is set on the match, and doesn't need to be 
// done manually, is this a good idea?
//
//

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

class lessc 
{
	private $buffer;
	private $out;
	private $env = array(); // environment stack
	private $count = 0; // temporary advance
	private $level = 0;
	private $line = 1; // the current line
	private $precedence = array(
		'+' => '0',
		'-' => '0',
		'*' => '1',
		'/' => '1',
	);

	public $importDisabled = false;

	public function __construct($fname = null)
	{
		if ($fname) $this->load($fname);
	}

	// load a css from file
	public function load($fname) 
	{
		if (!is_file($fname)) {
			throw new Exception('load error: failed to find '.$fname);
		}

		$this->file = $fname;
		$this->buffer = file_get_contents($fname);
	}

	public function parse($text = null)
	{
		if ($text) $this->buffer = $text;
		$this->reset();

		// trim whitespace on head
		if (preg_match('/^\s+/', $this->buffer, $m)) {
			$this->line  += substr_count($m[0], "\n");
			$this->buffer = ltrim($this->buffer);
		}

		$this->push(); // set up global scope
		$this->set('__tags', array('')); // equivalent to 1 in tag multiplication

		// get rid of all the comments
		$this->buffer = preg_replace(
			array('/\/\*(.*?)\*\//s', '/\/\/.*$/m'),
			array('', ''),
			$this->buffer);

		while (false !== ($dat = $this->readChunk())) {
			if (is_string($dat)) $this->out .= $dat;
		}

		// print_r($this->env);
		return $this->out;
	}

	// read a chunk off the head of the buffer
	// chunks are separated by ; (in most cases)
	private function readChunk()
	{
		if ($this->buffer == '') return false;	

		// import statement
		// todo: css spec says we can use url() for import
		// add support for media keyword
		// also traditional import must be at the top of the document (ignore?)
		//
		// also need to add support for @media directive
		try {
			$this->literal('@import')->string($s, $delim)->end()->advance();
			if ($this->importDisabled) return "/* import is disabled */\n";

			if (file_exists($s)) {
				$this->buffer = file_get_contents($s).";\n".$this->buffer;
			} else if (file_exists($s.'.less')) {
				$this->buffer = file_get_contents($s.'less').";\n".$this->buffer;
			} else {
				return '@import '.$delim.$s.$delim."\n";
			}

			// todo: this is dumb don't do this
			// make sure there are no comments in the imported file
			$this->buffer = preg_replace(
				array('/\/\*(.*?)\*\//s', '/\/\/.*$/m'),
				array('', ''),
				$this->buffer);

			return true;
		} catch (exception $ex) { $this->undo(); }

		// a variable
		try {
			$this->variable($name)->literal(':')->propertyValue($value)->end()->advance();
			$this->set('@'.$name, $value);
			return true;
		} catch (exception $ex) {
			$this->undo();
		}

		// a property
		try {
			$this->keyword($name)->literal(':')->propertyValue($value)->end()->advance();
			$this->set($name, $value);

			// we can print it right away if we are in global scope (makes no sense, but w/e)
			if ($this->level > 1)
				return true;
			else
				return $this->compileProperty($name, $this->compileValue($this->get($name)))."\n";
		} catch (exception $ex) {
			$this->undo();
		}
		
		// a block
		try {
			$this->tags($tags)->literal('{')->advance();
			$this->push();
			$this->set('__tags', $tags);

			return true;
		} catch (exception $ex) {
			$this->undo();
		}

		// leaving a block
		try {
			$this->literal('}')->advance();

			$tags = $this->get('__tags');	
			$env = $this->pop();
			unset($env['__tags']);

			$rtags = $this->multiplyTags($tags);

			foreach ($tags as $t)
				$this->set($t, $env);

			return $this->compileBlock($rtags, $env);
		} catch (exception $ex) {
			$this->undo();
		}	

		// look for a namespace to expand
		try {
			$this->tags($t, true, '>')->end()->advance();

			$env = $this->get(array_shift($t));

			while ($sub = array_shift($t)) {
				if (is_array($env[$sub])) $env = $env[$sub];
				else { 
					$env = null;
					break;
				}
			}

			if ($env == null) return true;

			// set all properties 
			ob_start();
			foreach ($env as $name => $value) {

				// if it is a block then render it
				if (!isset($value[0])) {
					$rtags = $this->multiplyTags(array($name));
					echo $this->compileBlock($rtags, $value);
				}

				$this->set($name, $value);
			}

			return ob_get_clean();
		} catch (exception $ex) { $this->undo(); }
		
		// ignore spare ; 
		try { 
			$this->literal(';')->advance();
			return true;
		} catch (exception $ex) { $this->undo(); }

		// something failed
		// print_r($this->env);
		$this->match("(.*?)(\n|$)", $m);
		throw new exception('Failed to parse line '.$this->line."\nOffending line: ".$m[1]);
	}


	/**
	 * consume functions
	 *
	 * they return instance of class so they can be chained
	 * any return vals are put into referenced arguments
	 */

	private function string(&$string, &$d = null)
	{
		try { 
			$this->literal('"');
			$delim = '"';
		} catch (exception $ex) {
			$this->literal("'");
			$delim = "'";
		}

		$this->to($delim, $string);
		if ($d) $d = $delim;

		return $this;
	}
	
	private function end()
	{
		try {
			$this->literal(';');
		} catch (exception $ex) { 
			// there is an end of block next, then no problem
			if (!$this->match('}', $m))
				throw new exception('parse error: failed to find end');
		}

		return $this;
	}

	// get a list of tags separated by commas
	private function tags(&$tags, $simple = false, $delim = ',')
	{
		$tags = array();
		while (1) {
			$this->tag($tmp, $simple);
			$tags[] = trim($tmp);

			try { $this->literal($delim); } 
			catch (Exception $ex) { break; }
		}

		return $this;
	}

	// match a single tag, aka the block identifier
	// $simple only match simple tags, no funny selectors allowed
	private function tag(&$tag, $simple = false)
	{
		if ($simple)
			$chars = '^,;{}\][>';
		else 
			$chars = '^,;{}';

		// can't start with a number
		if (!$this->match('(['.$chars.'0-9]['.$chars.']+)', $m))
			throw new exception('parse error: failed to parse tag');

		$tag = trim($m[1]);

		$this->count = strlen($m[0]);
		return $this;
	}

	// consume $what and following whitespace from the head of buffer
	private function literal($what)
	{
		if (!$this->match(preg_quote($what), $m)) {
			throw new 
				Exception('parse error: failed to prase literal '.$what);
		}

		$this->count = strlen($m[0]);
		return $this;
	}

	// consume & evaluate a value for a property from head of buffer
	// any list of properties is compessed into a single keyword variable
	// type information only needs to be preserved when doing math, and we 
	// shouldn't have to do math of a list of values
	private function propertyValue(&$value)
	{
		$out = array();

		// there must be at least one
		$this->expressionList($out[]);

		while (1) {
			try {
				$this->literal(',')->expressionList($out[]);
			} catch (exception $ex) { break; }
		}

		$out = array_map(array($this, 'compressValues'), $out);
		$value = $this->compressValues($out, ', ');

		return $this;
	}

	// evaluate a list of expressions separated by spaces from the 
	// head of the buffer
	private function expressionList(&$vals)
	{
		$vals = array();
		$this->expression($vals[]); // there should be at least one

		while (1) {
			try { $this->expression($tmp); } 
			catch (Exception $ex) { break; }

			$vals[] = $tmp;
		}

		return  $this;
	}

	// evaluate a group of values separated by operators
	private function expression(&$result)
   	{
		$this->value($lhs);
		$this->matchString =
			'('.implode('|',array_map(array($this, 'preg_quote'), array_keys($this->precedence))).')';

		$result = $this->expHelper($lhs, 0);

		return $this;
	}
	
	// used to recursively love infix equation with proper operator order
	private function expHelper($lhs, $minP)
	{
		// while there is an operator and the precedence is greater or equal to min
		while ($this->match($this->matchString, $m) && $this->precedence[$m[1]] >= $minP) {
			$this->count = strlen($m[0]);
			$this->value($rhs);

			// peek next op
			if ($this->match($this->matchString, $mi) & $this->precedence[$mi[1]] > $minP)
			{
				$rhs = $this->expHelper($rhs, $this->precedence[$mi[1]]);
			}

			$lhs = $this->evaluate($m[1], $lhs, $rhs);
		}
		return $lhs;
	}

	// consume a css value:
	// a keyword
	// a variable (includes accessor);
	// a color
	// a unit (em, px, pt, %, mm), can also have no unit 4px + 3;
	private function value(&$val) 
	{
		// look for accessor 
		// must be done before color
		try {
			$save = $this->count; // todo: replace with counter stack
			$this->accessor($a);
			$tmp = $this->get($a[0]);
			$val = $tmp[$a[1]];
			return $this;
		} catch (exception $ex) { $this->count = $save; /* $this->undo(); */ }

		try { return $this->unit($val);} catch (exception $ex) { /* $this->undo(); */ }

		try { return $this->color($val); } catch (exception $ex) { /* $this->undo(); */ }

		try { 
			$this->variable($name); 
			$val = $this->get('@'.$name);
			return $this;
		} catch (exception $ex) { /* $this->undo(); */ }

		try {
			$this->keyword($k);
			$val = array('keyword', $k);
			return $this;
		} catch (exception $ex) { /* $this->undo(); */ }

		throw new exception('parse error: failed to find value');
	}

	// the default set of units
	private $units = array(
		'px', '%', 'in', 'cm', 'mm', 'em', 'ex', 'pt', 'pc');

	// $units the allowed units
	// number is always allowed (is this okay?)
	private function unit(&$unit, $units = null)
	{
		if (!$units) $units = $this->units;

		if (!$this->match('([0-9]+(\.[0-9]+)?)('.implode('|', $units).')?', $m)) {
			throw new exception('parse error: failed to consume unit');
		}

		// throw on a default unit
		if (!$m[3]) $m[3] = 'number'; 

		$this->count = strlen($m[0]);

		$unit = array($m[3], $m[1]);
		return $this;
	}

	// todo: hue saturation lightness support (hsl)
	// need a function to convert hsl to rgb
	private function color(&$out)
	{
		$color = array('color');
		if($this->match('(#([0-9a-f]{6})|#([0-9a-f]{3}))', $m)) {
				if (strlen($m[3])) {
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

					// todo: this is retarded
					$color[$i] = $t * (256/$width) + $t * floor(16/$width);
				} 
				
				$this->count = strlen($m[0]);

		} else {
			$save = $this->count;
			try {
				$this->literal('rgb');
				
				try { 
					$this->literal('a');
					$count = 4;
				} catch (exception $ex) {
					$count = 3;
				}

				$this->literal('(');
				
				// grab the numbers and format
				foreach (range(1, $count) as $i) {
					$this->unit($color[], array('%'));
					if ($i != $count) $this->literal(',');

					if ($color[$i][0] == '%')
						$color[$i] = 255 * ($color[$i][1] / 100);
					else 
						$color[$i] = $color[$i][1];
				}

				$this->literal(')');

				$color = $this->fixColor($color);
			} catch (exception $ex) {
				$this->count = $save;

				throw new exception('failed to find color');
			}
		}

		$out = $color; // don't put things on out unless everything works out
		return $this;
	}

	private function variable(&$var)
	{
		$this->literal('@')->keyword($var);
		return $this;
	}

	private function accessor(&$var)
	{
		$this->tag($scope, true)->literal('[');

		// see if it is a variable
		try {
			$this->variable($name);
			$name = '@'.$name;
		} catch (exception $ex) {
			// try to see if it is a property
			try {
				$this->literal("'")->keyword($name)->literal("'");
			} catch (exception $ex) { 
				throw new exception('parse error: failed to parse accessor');
			}
		}

		$this->literal(']');

		$var = array($scope, $name);

		return $this;
	}

	// read a keyword off the head of the buffer
	private function keyword(&$word)
	{
		if (!$this->match('([\w_"][\w-_"]*)', $m)) {
			throw new Exception('parse error: failed to find keyword');
		}

		$this->count = strlen($m[0]);
		$word = $m[1];

		// if this is a url, then consume the rest of it
		if ($word == 'url') {
			$this->literal('(')->to(')', $url);
			$word.= '('.$url.')';
		}

		return $this;
	}

	private function to($what, &$out)
	{
		if (!$this->match('(.*?)'.preg_quote($what), $m))
			throw new exception('parse error: failed to consume to '.$what);

		$out = $m[1];
		$this->count = strlen($m[0]);

		return $this;
	}


	/**
	 * compile functions turn data into css code
	 */

	private function compileProperty($name, $value, $level = 0)
	{
		// find out how deep we are for indentation
		return str_repeat('  ', $level).
			$name.':'.$value.';';
	}



	private function compileBlock($rtags, $env)
   	{
		$props = 0;
		// print all the properties
		ob_start();
		foreach ($env as $name => $value) {
			// todo: change this, poor hack
			// make a better name storage system!!! (value types are fine)
			if ($value[0] && $name{0} != '@') { // isn't a block because it has a type and isn't a var
				echo $this->compileProperty($name, $this->compileValue($value), 1)."\n";
				$props++;
			}
		}
		$list = ob_get_clean();

		if ($props == 0) return true;

		// do some formatting
		if ($props == 1) $list = ' '.trim($list).' ';
		return implode(",\n", $rtags).' {'.($props  > 1 ? "\n" : '').
			$list."}\n";
	}

	// todo replace render color
	private function compileValue($value)
	{
		switch ($value[0]) {
		case 'color':
			return $this->compileColor($value);

		case 'keyword':
			return $value[1];

		case 'number':
			return $value[1];

		default: // assumed to be a unit
			return $value[1].$value[0];
		}
	}


	private function compileColor($c)
	{
		if (count($c) == 5) { // rgba
			return 'rgba('.$c[1].','.$c[2].','.$c[3].','.$c[4].')';
		}

		return '#'.dechex($c[1]).dechex($c[2]).dechex($c[3]);
	}


	/** 
	 * arithmetic evaluator and operators
	 */

	// evalue an operator
	// this is a messy function, probably a better way to do it
	private function evaluate($op, $lft, $rgt)
	{
		if ($lft [0] == 'color' && $rgt[0] == 'color') {
			return $this->op_color_color($op, $lft, $rgt);
		}

		if ($lft[0] == 'color') {
			return $this->op_color_number($op, $lft, $rgt);
		}

		if ($rgt[0] == 'color') {
			return $this->op_number_color($op, $lft, $rgt);
		}

		// default number number
		return $this->op_number_number($op, $lft, $rgt);
	}

	private function op_number_number($op, $lft, $rgt)
	{
		if ($rgt[0] == '%') $rgt[1] /= 100;

		// figure out the type
		if ($rgt[0] == 'number' || $rgt[0] == '%') $type = $lft[0];
		else $type = $rgt[0];

		$num = array($type);

		switch($op) {
		case '+':
			$num[] = $lft[1] + $rgt[1];
			break;
		case '*':
			$num[] = $lft[1] * $rgt[1];
			break;
		case '-':
			$num[] = $lft[1] - $rgt[1];
			break;
			case '/';
			if ($rgt[1] == 0) throw new exception("parse error: can't divide by zero");
			$num[] = $lft[1] / $rgt[1];
			break;
		default:
			throw new exception('parse error: number op number failed on op '.$op);
		}

		return $num;
	}

	private function op_number_color($op, $lft, $rgt)
	{
		if ($op == '+' || $op = '*') {
			return $this->op_color_number($op, $rgt, $lft);
		}
	}

	private function op_color_number($op, $lft, $rgt)
	{
		if ($rgt[0] == '%') $rgt[1] /= 100;

		return $this->op_color_color($op, $lft, 
			array('color', $rgt[1], $rgt[1], $rgt[1]));
	}

	private function op_color_color($op, $lft, $rgt)
	{
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


	/**
	 * functions for controlling the environment
	 */

	// get something out of the env
	// search from the head of the stack down
	private function get($name)
	{
		for ($i = count($this->env) - 1; $i >= 0; $i--)
			if ($this->env[$i][$name]) return $this->env[$i][$name];

		return null;
	}

	// set something in the current env
	private function set($name, $value)
	{
		$this->env[count($this->env) - 1][$name] = $value;
	}

	// compress a list of values into a single type
	// if the list contains one thing, then return that thing
	// if list is full of things, implode whem with delim and return as keyword
	private function compressValues($values, $delim = ' ')
	{
		if (count($values) == 1) return $values[0];
		
		return array('keyword', implode($delim, 
				array_map(array($this, 'compileValue'), $values)));
	}

	// push a new environment stack
	private function push()
	{
		$this->level++;
		$this->env[] = array();
	}

	// pop environment off the stack
	private function pop()
	{
		if ($this->level == 1) 
			throw new exception('parse error: unexpected end of block');

		$this->level--;
		return array_pop($this->env);
	}


	/**
	 * misc functions
	 */

	// match text from the head while skipping $count characters
	private function match($regex, &$out) 
	{
		$r = '/^.{'.$this->count.'}'.$regex.'\s*/is';
		return preg_match($r, $this->buffer, $out);
	}

	// make sure a color's components don't go out of bounds
	private function fixColor($c)
	{
		for ($i = 1; $i < 4; $i++) {
			if ($c[$i] < 0) $c[$i] = 0;
			if ($c[$i] > 255) $c[$i] = 255;
			$c[$i] = floor($c[$i]);
		}
		return $c;
	}

	private function preg_quote($what)
	{
		// I don't know why it doesn't include it by default
		return preg_quote($what, '/');
	}

	// reset all internal state to default
	private function reset()
	{
		$this->out = '';
		$this->env = array();
		$this->line = 1;
		$this->count = 0;
	}

	// advance the buffer n places
	private function advance()
	{
		// this is probably slow
		$tmp = substr($this->buffer, 0, $this->count);
		$this->line += substr_count($tmp, "\n");

		$this->buffer = substr($this->buffer, $this->count); 
		$this->count = 0;
	}

	//  reset the temporary advance
	private function undo()
	{
		$this->count = 0;
	}

	// find the cartesian product of all tags in stack
	// todo; don't use recursion
	private function multiplyTags($tags, $d = null)
	{
		if ($d === null) $d = count($this->env) - 1;

		$parents = $d == 0 ? $this->env[$d]['__tags'] 
			: $this->multiplyTags($this->env[$d]['__tags'], $d - 1);

		$rtags = array();
		foreach ($parents as $p) {
			foreach ($tags as $t) {
				$rtags[] = trim($p.($t{0} == ':' ? '' : ' ').$t);
			}
		}

		return $rtags;
	}
}


?>
