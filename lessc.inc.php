<?php

/**
 * less.inc.php
 * v0.1.6
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

	// delayed types
	private $dtypes = array('expression', 'variable');

	// the default set of units
	private $units = array(
		'px', '%', 'in', 'cm', 'mm', 'em', 'ex', 'pt', 'pc', 's');

	public $importDisabled = false;
	public $importDir = '';

	public function __construct($fname = null)
	{
		if ($fname) $this->load($fname);

		$this->matchString =
			'('.implode('|',array_map(array($this, 'preg_quote'), array_keys($this->precedence))).')';
	}

	// load a css from file
	public function load($fname) 
	{
		if (!is_file($fname)) {
			throw new Exception('load error: failed to find '.$fname);
		}
		$pi = pathinfo($fname);

		$this->file = $fname;
		$this->importDir = $pi['dirname'].'/';
		$this->buffer = file_get_contents($fname);
	}

	public function parse($text = null)
	{
		if ($text) $this->buffer = $text;
		$this->reset();

		$this->push(); // set up global scope
		$this->set('__tags', array('')); // equivalent to 1 in tag multiplication

		$this->buffer = $this->removeComments($this->buffer);

		// trim whitespace on head
		if (preg_match('/^\s+/', $this->buffer, $m)) {
			$this->line  += substr_count($m[0], "\n");
			$this->buffer = ltrim($this->buffer);
		}

		while (false !== ($dat = $this->readChunk())) {
			if (is_string($dat)) $this->out .= $dat;
		}

		if ($count = count($this->env) > 1) {
			throw new 
				exception('Failed to parse '.(count($this->env) - 1).
				' unclosed block'.($count > 1 ? 's' : ''));
		}

		// print_r($this->env);
		return $this->out;
	}


	// read a chunk off the head of the buffer
	// chunks are separated by ; (in most cases)
	private function readChunk()
	{
		if ($this->buffer == '') return false;	

		// todo: media directive
		// media screen {
		// 	blocks
		// }

		// a property
		try {
			$this->keyword($name)->literal(':')->propertyValue($value)->end()->advance();
			$this->append($name, $value);

			// we can print it right away if we are in global scope (makes no sense, but w/e)
			if ($this->level > 1)
				return true;
			else
				return $this->compileProperty($name, 
					array($this->getVal($name)))."\n";
		} catch (exception $ex) {
			$this->undo();
		}

		// entering a block
		try {
			$this->tags($tags);
			
			// it can only be a function if there is one tag
			if (count($tags) == 1) {
				try {
					$save = $this->count;
					$this->argumentDef($args);
				} catch (exception $ex) {
					$this->count = $save;
				}
			}

			$this->literal('{')->advance();
			$this->push();

			//  move @ tags out of variable namespace!
			foreach($tags as &$tag) {
				if ($tag{0} == "@") $tag[0] = "%";
			}

			$this->set('__tags', $tags);
			if (isset($args)) $this->set('__args', $args);	

			return true;
		} catch (exception $ex) {
			$this->undo();
		}

		// leaving a block
		try {
			$this->literal('}')->advance();

			$tags = $this->multiplyTags(); 

			$env = end($this->env);
			$ctags = $env['__tags'];
			unset($env['__tags']);

			// insert the default arguments
			if (isset($env['__args'])) {
				foreach ($env['__args'] as $arg) {
					if (isset($arg[1])) {
						$this->prepend('@'.$arg[0], $arg[1]);
					}	
				}
			}
			
			if (!empty($tags))
				$out = $this->compileBlock($tags, $env);

			$this->pop();

			// make the block(s) available in the new current scope
			foreach ($ctags as $t) {
				// if the block already exists then merge 
				if ($this->get($t, array(end($this->env)))) {
					$this->merge($t, $env);
				} else {
					$this->set($t, $env);
				}
			}

			return isset($out) ? $out : true;
		} catch (exception $ex) {
			$this->undo();
		}	

		// look for import
		try {
			$this->import($url, $media)->advance();
			if ($this->importDisabled) return "/* import is disabled */\n";

			$full = $this->importDir.$url;

			if (file_exists($file = $full) || file_exists($file = $full.'.less')) {
				$this->buffer = 
					$this->removeComments(file_get_contents($file).";\n".$this->buffer);
				return true;
			}

			return '@import url("'.$url.'")'.($media ? ' '.$media : '').";\n";
		} catch (exception $ex) {
			$this->undo();
		}

		// setting a variable
		try {
			$this->variable($name)->literal(':')->propertyValue($value)->end()->advance();
			$this->append('@'.$name, $value);
			return true;
		} catch (exception $ex) {
			$this->undo();
		}


		// look for a namespace/function to expand
		// todo: this catches a lot of invalid syntax because tag 
		// consumer is liberal. This causes errors to be hidden
		try {
			$this->tags($tags, true, '>');

			//  move @ tags out of variable namespace
			foreach($tags as &$tag) {
				if ($tag{0} == "@") $tag[0] = "%";
			}

			// look for arguments
			$save = $this->count;
			try { 
				$this->argumentValues($argv); 
			} catch (exception $ex) { $this->count = $save; }

			$this->end()->advance();

			// find the final environment
			$env = $this->get(array_shift($tags));

			while ($sub = array_shift($tags)) {
				if (isset($env[$sub]))  // todo add a type check for environment
					$env = $env[$sub];
				else { 
					$env = null;
					break;
				}
			}

			if ($env == null) return true;

			// if we have arguments then insert them
			if (!empty($env['__args'])) {
				foreach($env['__args'] as $arg) {
					$name = $arg[0];
					$value = is_array($argv) ? array_shift($argv) : null;
					// copy default value if there isn't one supplied
					if ($value == null && isset($arg[1])) 
						$value = $arg[1];

					// if ($value == null) continue; // don't define so it can search up 

					// create new entry if var doesn't exist in scope
					if (isset($env['@'.$name])) {
						array_unshift($env['@'.$name], $value);
					} else {
						// new element
						$env['@'.$name] = array($value);
					}
				}
			} 

			// set all properties 
			ob_start();
			foreach ($env as $name => $value) {
				// if it is a block then render it
				if (!isset($value[0])) {
					$rtags = $this->multiplyTags(array($name));
					echo $this->compileBlock($rtags, $value);
				}

				// copy everything except metadata
				if (!preg_match('/^__/', $name)) {
					// don't overwrite previous value, look in current env for name
					if ($this->get($name, array(end($this->env)))) {
						while ($tval = array_shift($value))
							$this->append($name, $tval);
					} else 
						$this->set($name, $value); 
				}
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

	// look for an import statement on the head of the buffer
	private function import(&$url, &$media)
	{
		$this->literal('@import');
		$save = $this->count;
		try {
			// todo: merge this with the keyword url('')
			$this->literal('url(')->string($url)->literal(')');
		} catch (exception $ex) {
			$this->count = $save;
			$this->string($url);
		}

		$this->to(';', $media);

		return $this;
	}

	private function string(&$string, &$d = null)
	{
		try { 
			$this->literal('"', true);
			$delim = '"';
		} catch (exception $ex) {
			$this->literal("'", true);
			$delim = "'";
		}

		$this->to($delim, $string);

		if (!isset($d)) $d = $delim;

		return $this;
	}
	
	private function end()
	{
		try {
			$this->literal(';');
		} catch (exception $ex) { 
			// there is an end of block next, then no problem
			if (strlen($this->buffer) <= $this->count || $this->buffer{$this->count} != '}')
				throw new exception('parse error: failed to find end');
		}

		return $this;
	}

	// gets a list of property values separated by ; between ( and )
	private function argumentValues(&$args, $delim = ';')
	{
		$this->literal('(');

		$values = array();
		while (true){ 
			try {
				$this->propertyValue($values[])->literal(';');
			} catch (exception $ex) { break; }
		}

		$this->literal(')');
		$args = $values;
		
		return $this;
	}

	// consume agument definition, variable names with optional value
	private function argumentDef(&$args, $delim = ';') 
	{
		$this->literal('(');

		$values = array();
		while (true) {
			try { 
				$arg = array();
				$this->variable($arg[]);
				// look for a default value
				try {
					$this->literal(':')->propertyValue($value);
					$arg[] = $value;
				} catch (exception $ax) { }

				$values[] = $arg;
				$this->literal($delim);
			} catch (exception $ex) {
				break;
			}
		}

		$this->literal(')');
		$args = $values;
		
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
	// this accepts spaces so it can mis comments...
	private function tag(&$tag, $simple = false)
	{
		if ($simple)
			$chars = '^,:;{}\][>\(\)';
		else 
			$chars = '^,;{}\(\)';

		// can't start with a number
		if (!$this->match('(['.$chars.'0-9]['.$chars.']*)', $m))
			throw new exception('parse error: failed to parse tag');

		$tag = trim($m[1]);

		return $this;
	}

	// consume $what and following whitespace from the head of buffer
	private function literal($what)
	{
		// if $what is one char we can speed things up
		if ((strlen($what) == 1 && $this->count < strlen($this->buffer) && $what != $this->buffer{$this->count}) ||
			!$this->match($this->preg_quote($what), $m)) 
		{
			throw new 
				Exception('parse error: failed to prase literal '.$what);
		}
		return $this;
	}

	// consume list of values for property
	private function propertyValue(&$value)
	{
		$out = array();

		while (1) {
			try { 
				$this->expressionList($out[]);
				$this->literal(','); } 
			catch (exception $ex) { break; }
		}
		
		if (!empty($out)) {
			$out = array_map(array($this, 'compressValues'), $out);
			$value = $this->compressValues($out, ', ');
		}

		return $this;
	}

	// evaluate a list of expressions separated by spaces 
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
		try {
			$this->literal('(')->expression($exp)->literal(')');
			$lhs = $exp;
		} catch (exception $ex) {
			$this->value($lhs);
		}
		$result = $this->expHelper($lhs, 0);
		return $this;
	}


	// used to recursively love infix equation with proper operator order
	private function expHelper($lhs, $minP)
	{
		// while there is an operator and the precedence is greater or equal to min
		while ($this->match($this->matchString, $m) && $this->precedence[$m[1]] >= $minP) {
			// check for subexp
			try {
				$this->literal('(')->expression($exp)->literal(')');
				$rhs = $exp;
			} catch (exception $ex) {
				$this->value($rhs);
			}

			// find out if next up needs rhs
			if ($this->peek($this->matchString, $mi) && $this->precedence[$mi[1]] > $minP) {
				$rhs = $this->expHelper($rhs, $this->precedence[$mi[1]]);
			}

			// todo: find a way to precalculate non-delayed types
			if (in_array($rhs[0], $this->dtypes) || in_array($lhs[0], $this->dtypes))
				$lhs = array('expression', $m[1], $lhs, $rhs);
			else
				$lhs = $this->evaluate($m[1], $lhs, $rhs);
		}
		return $lhs;
	}

	// consume a css value:
	// a keyword
	// a variable (includes accessor);
	// a color
	// a unit (em, px, pt, %, mm), can also have no unit 4px + 3;
	// a string 
	private function value(&$val) 
	{
		try { 
			return $this->unit($val);
		} catch (exception $ex) { /* $this->undo(); */ }

		// look for accessor 
		// must be done before color
		try {
			$save = $this->count; // todo: replace with counter stack
			$this->accessor($a);
			$tmp = $this->get($a[0]); // get env
			$val = end($tmp[$a[1]]); // get latest var

			return $this;
		} catch (exception $ex) { $this->count = $save; /* $this->undo(); */ }

		try { 
			return $this->color($val); 
		} catch (exception $ex) { /* $this->undo(); */ }

		try {
			$save = $this->count;
			$this->func($f);

			$val = array('string', $f);

			return $this;
		} catch (exception $ex) { $this->count = $save; }

		// a string
		try { 
			$save = $this->count;
			$this->string($tmp, $d);
			$val = array('string', $d.$tmp.$d);
			return $this;
		} catch (exception $ex) { $this->count = $save; }

		try {
			$this->keyword($k);
			$val = array('keyword', $k);
			return $this;
		} catch (exception $ex) { /* $this->undo(); */ }



		// try to get a variable
		try { 
			$this->variable($name); 
			$val = array('variable', '@'.$name);

			return $this;
		} catch (exception $ex) { /* $this->undo(); */ }

		throw new exception('parse error: failed to find value');
	}

	// $units the allowed units
	// number is always allowed (is this okay?)
	private function unit(&$unit, $units = null)
	{
		if (!$units) $units = $this->units;

		if (!$this->match('(-?[0-9]*(\.)?[0-9]+)('.implode('|', $units).')?', $m)) {
			throw new exception('parse error: failed to consume unit');
		}

		// throw on a default unit
		if (!isset($m[3])) $m[3] = 'number'; 

		$unit = array($m[3], $m[1]);
		return $this;
	}

	// todo: hue saturation lightness support (hsl)
	// need a function to convert hsl to rgb
	private function color(&$out)
	{
		$color = array('color');
		if($this->match('(#([0-9a-f]{6})|#([0-9a-f]{3}))', $m)) {
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

				// todo: this is retarded
				$color[$i] = $t * (256/$width) + $t * floor(16/$width);
			} 

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

	// read a css function off the head of the buffer
	private function func(&$func)
	{
		$this->keyword($fname)->literal('(')->to(')', $args);

		$func = $fname.'('.$args.')';
		return $this;
	}

	// read a keyword off the head of the buffer
	private function keyword(&$word)
	{
		if (!$this->match('([\w_\-!"][\w\-_"]*)', $m)) {
			throw new Exception('parse error: failed to find keyword');
		}

		$word = $m[1];
		return $this;
	}

	// this ignores comments because it doesn't grab by token
	private function to($what, &$out)
	{
		if (!$this->match('(.*?)'.$this->preg_quote($what), $m))
			throw new exception('parse error: failed to consume to '.$what);

		$out = $m[1];

		return $this;
	}


	/**
	 * compile functions turn data into css code
	 */
	private function compileBlock($rtags, $env)
   	{
		// don't render functions
		foreach ($rtags as $i => $tag) {
			if (preg_match('/( |^)%/', $tag))
				unset($rtags[$i]);
		}
		if (empty($rtags)) return '';

		$props = 0;
		// print all the properties
		ob_start();
		foreach ($env as $name => $value) {
			// todo: change this, poor hack
			// make a better name storage system!!! (value types are fine)
			// but.. don't render special properties (blocks, vars, metadata)
			if (isset($value[0]) && $name{0} != '@' && $name != '__args') { 
				echo $this->compileProperty($name, $value, 1)."\n";
				$props++;
			}
		}
		$list = ob_get_clean();

		if ($props == 0) return true;

		// do some formatting
		if ($props == 1) $list = ' '.trim($list).' ';
		return implode(", ", $rtags).' {'.($props  > 1 ? "\n" : '').
			$list."}\n";
	}

	private function compileProperty($name, $value, $level = 0)
	{
		// compile all repeated properties
		foreach ($value as $v)
			$props[] = str_repeat('  ', $level).
				$name.':'.$this->compileValue($v).';';

		return implode("\n", $props);
	}

	private function compileValue($value)
	{
		switch ($value[0]) {
		case 'list':
			return implode($value[1], array_map(array($this, 'compileValue'), $value[2]));
		case 'expression':
			return $this->compileValue($this->evaluate($value[1], $value[2], $value[3]));

		case 'variable':
			$tmp =  $this->compileValue(
				$this->getVal($value[1], 
					$this->pushName($value[1]))
			);
			$this->popName();

			return $tmp;

		case 'string':
			// search for values inside the string
			$replace = array();
			if (preg_match_all('/{(@[\w-_][0-9\w-_]*)}/', $value[1], $m)) {
				foreach($m[1] as $name) {
					if (!isset($replace[$name]))
						$replace[$name] = $this->compileValue(array('variable', $name));
				}
			}
			foreach ($replace as $var=>$val)
			   $value[1] = str_replace('{'.$var.'}', $val, $value[1]);

			return $value[1];

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

		$out = '#';
		foreach (range(1,3) as $i)
			$out .= ($c[$i] < 16 ? '0' : '').dechex($c[$i]);
		return $out;
	}


	/** 
	 * arithmetic evaluator and operators
	 */

	// evalue an operator
	// this is a messy function, probably a better way to do it
	private function evaluate($op, $lft, $rgt)
	{
		$pushed = 0;
		// figure out what expressions and variables are equal to
		while (in_array($lft[0], $this->dtypes)) 
		{
			if ($lft[0] == 'expression')
				$lft = $this->evaluate($lft[1], $lft[2], $lft[3]);
			else if ($lft[0] == 'variable') {
				$lft = $this->getVal($lft[1], $this->pushName($lft[1]), array('number', 0));
				$pushed++;
			}

		}
		while ($pushed != 0) { $this->popName(); $pushed--; }

		while (in_array($rgt[0], $this->dtypes))
		{
			if ($rgt[0] == 'expression')
				$rgt = $this->evaluate($rgt[1], $rgt[2], $rgt[3]);
			else if ($rgt[0] == 'variable') {
				$rgt = $this->getVal($rgt[1], $this->pushName($rgt[1]), array('number', 0));
				$pushed++;
			}
		}
		while ($pushed != 0) { $this->popName(); $pushed--; }

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
	// $env what environment to search in
	private function get($name, $env = null)
	{
		if (empty($env)) $env = $this->env;

		for ($i = count($env) - 1; $i >= 0; $i--)
			if (isset($env[$i][$name])) return $env[$i][$name];

		return null;
	}


	// get the most recent value of a variable
	// return default if it isn't found
	// $skip is number of vars to skip
	private function getVal($name, $skip = 0, $default = array('keyword', ''))
	{
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
	private function merge($name, $value)
	{
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

	// set something in the current env
	private function set($name, $value)
	{
		$this->env[count($this->env) - 1][$name] = $value;
	}

	// append to array in the current env
	private function append($name, $value)
	{
		$this->env[count($this->env) - 1][$name][] = $value;
	}

	// put on the front of the value
	private function prepend($name, $value) 
	{
		if (isset($this->env[count($this->env) - 1][$name]))
			array_unshift($this->env[count($this->env) - 1][$name], $value);
		else $this->append($name, $value);
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

	// functions for manipulating the expand stack
	private $expandStack = array();

	// push name on expand stack and return its count
	// before being pushed
	private function pushName($name) {
		$count = array_count_values($this->expandStack);
		$count = isset($count[$name]) ? $count[$name] : 0;

		$this->expandStack[] = $name;
		return $count;
	}

	// pop name of expand stack and return it
	private function popName() {
		return array_pop($this->expandStack);
	}


	// remove comments from $text
	// todo: make it work for all functions, not just url
	private function removeComments($text)
   	{
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


	// match text from the head while skipping $count characters
	// advances the temp counter if it succeeds
	private function match($regex, &$out, $eatWhitespace = true) 
	{
		// if ($this->count > 100) echo '-- '.$this->count."\n";
		$r = '/^.{'.$this->count.'}'.$regex.($eatWhitespace ? '\s*' : '').'/is';
		if (preg_match($r, $this->buffer, $out)) {
			$this->count = strlen($out[0]);
			return true;
		} 

		return false;
		
	}

	private function peek($regex, &$out = null)
	{
		return preg_match('/^.{'.$this->count.'}'.$regex.'/is', $this->buffer, $out);
	}


	// compress a list of values into a single type
	// if the list contains one thing, then return that thing
	private function compressValues($values, $delim = ' ')
	{
		if (count($values) == 1) return $values[0];
		return array('list', $delim, $values);
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
	private function multiplyTags($tags = array(' '), $d = null)
	{
		if ($d === null) $d = count($this->env) - 1;

		$parents = $d == 0 ? $this->env[$d]['__tags'] 
			: $this->multiplyTags($this->env[$d]['__tags'], $d - 1);

		$rtags = array();
		foreach ($parents as $p) {
			foreach ($tags as $t) {
				if ($t{0} == '@') continue; // skip functions
				$rtags[] = trim($p.($t{0} == ':' ? '' : ' ').$t);
			}
		}

		return $rtags;
	}


	/**
	 * static utility functions
	 */

	// compile to $in to $out if $in is newer than $out
	// returns true when it compiles, false otherwise
	public static function ccompile($in, $out)
	{
		if (!is_file($out) || filemtime($in) > filemtime($out)) {
			$less = new lessc($in);
			file_put_contents($out, $less->parse());
			return true;
		}

		return false;
	}
}


?>
