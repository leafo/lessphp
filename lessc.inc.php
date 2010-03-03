<?php

/**
 * less.inc.php
 * v0.2.0
 *
 * less css compiler
 * adapted from http://lesscss.org/docs.html
 *
 * leaf corcoran <leafot@gmail.com>
 * leafo.net
 */

//
// investigate trouble with  
// fix the alpha value with color when using a percent
//

class lessc {
	private $buffer;
	private $count;
	private $line;
	private $expandStack;

	private $env = array();

	static private $precedence = array(
		'+' => '0',
		'-' => '0',
		'*' => '1',
		'/' => '1',
	);
	static private $operatorString; // regex string to any of the operators

	static private $dtypes = array('expression', 'variable'); // types with delayed computation
	static private $units = array(
		'px', '%', 'in', 'cm', 'mm', 'em', 'ex', 'pt', 'pc', 's');

	public $importDisabled = false;
	public $importDir = '';

	// compile chunk off the head of buffer
	function chunk() {
		if (empty($this->buffer)) return false;
		$s = $this->seek();

		// a property
		if ($this->keyword($key) && $this->literal(':') && $this->propertyValue($value) && $this->end()) {
			$this->append($key, $value);

			if (count($this->env) == 1)
				return $this->compileProperty($key, array($value))."\n";
			else
				return true;
		} else {
			$this->seek($s);
		}

		// opening abstract block
		if ($this->tag($tag, true) && $this->argumentDef($args) && $this->literal('{')) {
			$this->push();

			// move out of variable scope
			if ($tag{0} == "@") $tag[0] = "%";

			$this->set('__tags', array($tag));
			if (isset($args)) $this->set('__args', $args);

			return true;
		} else {
			$this->seek($s);
		}

		// opening css block
		if ($this->tags($tags) && $this->literal('{')) {
			//  move @ tags out of variable namespace!
			foreach($tags as &$tag) {
				if ($tag{0} == "@") $tag[0] = "%";
			}

			$this->push();
			$this->set('__tags', $tags);	

			return true;
		} else {
			$this->seek($s);
		}

		// closing block
		if ($this->literal('}')) {
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
		} 
		
		// import statement
		if ($this->import($url, $media)) {
			if ($this->importDisabled) return "/* import is disabled */\n";

			$full = $this->importDir.$url;
			if (file_exists($file = $full) || file_exists($file = $full.'.less')) {
				$loaded = $this->removeComments(ltrim(file_get_contents($file).";"));
				$this->buffer = substr($this->buffer, 0, $this->count).$loaded.substr($this->buffer, $this->count);
				return true;
			}

			return '@import url("'.$url.'")'.($media ? ' '.$media : '').";\n";
		}

		// setting variable
		if ($this->variable($name) && $this->literal(':') && $this->propertyValue($value) && $this->end()) {
			$this->append('@'.$name, $value);
			return true;
		} else {
			$this->seek($s);
		}

		// mixin/function expand
		if ($this->tags($tags, true, '>')) {
			//  move @ tags out of variable namespace
			foreach($tags as &$tag) {
				if ($tag{0} == "@") $tag[0] = "%";
			}

			// look for arguments
			$this->argumentValues($argv);

			if (!$this->end()) {
				$this->seek($s);
				return false; // don't do this here
			}
			
			// find the referenced environment
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
				// skip the metatdata
				if (preg_match('/^__/', $name)) continue;

				// if it is a block then render it
				if (!isset($value[0])) {
					$rtags = $this->multiplyTags(array($name));
					echo $this->compileBlock($rtags, $value);
				}

				// copy the block's data
				// don't overwrite previous value, look in current env for name
				if ($this->get($name, array(end($this->env)))) {
					while ($tval = array_shift($value))
						$this->append($name, $tval);
				} else 
					$this->set($name, $value); 
			}

			return ob_get_clean();
		}

		// spare ;
		if ($this->literal(';')) return true;

		return false; // couldn't match anything, throw error
	}

	// recursively find the cartesian product of all tags in stack
	function multiplyTags($tags = array(' '), $d = null) {
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

	// a single expression
	function expression(&$out) {
		$s = $this->seek();
		$needWhite = true;
		if ($this->literal('(') && $this->expression($exp) && $this->literal(')')) {
			$lhs = $exp;
			$needWhite = false;
		} elseif ($this->seek($s) && $this->value($val)) {
			$lhs = $val;
		} else {
			return false;
		}

		$out = $this->expHelper($lhs, 0, $needWhite);
		return true;
	}

	// resursively parse infix equation with $lhs at precedence $minP
	function expHelper($lhs, $minP, $needWhite = true) {
		$ss = $this->seek();
		// try to find a valid operator
		while ($this->match(self::$operatorString.($needWhite ? '\s+' : ''), $m) && self::$precedence[$m[1]] >= $minP) {
			$needWhite = true;
			// get rhs
			$s = $this->seek();
			if ($this->literal('(') && $this->expression($exp) && $this->literal(')')) {
				$needWhite = false;
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

	// a single value
	function value(&$value) {
		/*
		switch (true) {
			case $this->unit($value): return true;
			// ...
		}
		 */

		// try a unit
		if ($this->unit($value)) return true;	

		// accessor 
		// must be done before color
		if ($this->accessor($a)) {
			$tmp = $this->get($a[0]);
			if (isset($tmp[$a[1]]))
				$value = end($tmp[$a[1]]); // fails if $tmp isn't an environment
			return true;
		}
		
		// color
		if ($this->color($value)) return true;

		// css function
		if ($this->func($f)) {
			$value = array('string', $f);
			return true;
		}

		// string
		if ($this->string($tmp, $d)) {
			$value = array('string', $d.$tmp.$d);
			return true;
		}

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

	// an import statement
	function import(&$url, &$media) {
		$s = $this->seek();
		if (!$this->literal('@import')) return false;

		// there are many forms of import
		// @import "something.css" media;
		// @import url("something.css") media;
		// @import url(something.css) media; 

		if ($this->literal('url(')) $parens = true; else $parens = false;

		if (!$this->string($url)) {
			if ($parens && $this->to(')', $url)) {
				$parens = false; // got em
			} else {
				$this->seek($s);
				return false;
			}
		}

		if ($parens && !$this->literal(')')) {
			$this->seek($s);
			return false;
		}

		// now the rest is media
		return $this->to(';', $media);
	}

	// a scoped value accessor
	function accessor(&$var) {
		$s = $this->seek();
		if (!$this->tag($scope, true) || !$this->literal('[')) {
			$this->seek($s);
			return false;
		}

		if ($scope{0} == '@') $scope{0} = '%';

		// either it is a variable or a property
		// why is a property wrapped in quotes, who knows!
		if ($this->variable($name)) {
			$name = '@'.$name;
		} elseif($this->literal("'") && $this->keyword($name) && $this->literal("'")) {
			// .. $this->count is messed up if we wanted to test another access type
		} else {
			$this->seek($s);
			return false;
		}

		if (!$this->literal(']')) {
			$this->seek($s);
			return false;
		}

		$var = array($scope, $name);

		return true;
	}

	// a string 
	function string(&$string, &$d = null) {
		$s = $this->seek();
		if ($this->literal('"')) {
			$delim = '"';
		} else if($this->literal("'")) {
			$delim = "'";
		} else {
			return false;
		}

		if (!$this->to($delim, $string)) {
			$this->seek($s);
			return false;
		}
		
		$d = $delim;
		return true;
	}

	// a numerical unit
	function unit(&$unit, $allowed = null) {
		if (!$allowed) $allowed = self::$units;

		if ($this->match('(-?[0-9]*(\.)?[0-9]+)('.implode('|', $allowed).')?', $m)) {
			if (!isset($m[3])) $m[3] = 'number';
			$unit = array($m[3], $m[1]);
			return true;
		}

		return false;
	}

	// a color
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

			// grab the numbers and format
			foreach (range(1, $count) as $i) {
				if (!$this->unit($color[], array('%')) || !($i == $count || $this->literal(','))) {
					$this->seek($s);
					return false;
				}

				if ($color[$i][0] == '%')
					$color[$i] = 255 * ($color[$i][1] / 100);
				else
					$color[$i] = $color[$i][1];
			}

			if (!$this->literal(')')) {
				$this->seek($s);
				return false;
			}
		
			$out = $this->fixColor($color);
			return true;
		}

		return false;
	}

	// consume a list of property values delimited by ; and wrapped in ()
	function argumentValues(&$args, $delim = ';') {
		$s = $this->seek();
		if (!$this->literal('(')) return false;

		$values = array();
		while ($this->propertyValue($value)) {
			$values[] = $value;
			if (!$this->literal($delim)) break;
		}

		if (!$this->literal(')')) {
			$this->seek($s);
			return false;
		}
		
		$args = $values;
		return true;
	}

	// consume an argument definition list surrounded by (), each argument is a variable name with optional value
	function argumentDef(&$args, $delim = ';') {
		$s = $this->seek();
		if (!$this->literal('(')) return false;

		$values = array();
		while ($this->variable($vname)) {
			$arg = array($vname);
			if ($this->literal(':') && $this->propertyValue($value)) {
				$arg[] = $value;
				// let the : slide if there is no value
			}

			$values[] = $arg;
			if (!$this->literal($delim)) break;
		}

		if (!$this->literal(')')) {
			$this->seek($s);
			return false;
		}

		$args = $values;
		return true;
	}

	// consume a list of tags
	function tags(&$tags, $simple = false, $delim = ',') {
		$tags = array();
		while ($this->tag($tt, $simple)) {
			$tags[] = $tt;
			if (!$this->literal($delim)) break;
		}
		if (count($tags) == 0) return false;

		return true;
	}

	// a single tag
	function tag(&$tag, $simple = false) {
		if ($simple)
			$chars = '^,:;{}\][>\(\) ';
		else
			$chars = '^,;{}';

		// can't start with a number
		if (!$this->match('(['.$chars.'0-9]['.$chars.']*)', $m))
			return false;

		$tag = trim($m[1]);
		return true;
	}

	// a css function
	function func(&$func) {
		$s = $this->seek();

		if ($this->keyword($fname) && $this->literal('(') && $this->to(')', $args)) {
			$func = $fname.'('.$args.')';
			return true;
		}
		$this->seek($s);
		return false;
	}

	// consume a less variable
	function variable(&$name) {
		$s = $this->seek();
		if ($this->literal('@', false) && $this->keyword($name)) {
			return true;	
		}
		return false;
	}

	// consume a keyword
	function keyword(&$word) {
		if ($this->match('([\w_\-!"][\w\-_"]*)', $m)) {
			$word = $m[1];
			return true;
		}
		return false;
	}

	// consume an end of statement delimiter
	function end() {
		if ($this->literal(';'))
			return true;
		elseif ($this->buffer{$this->count} == '}') {
			// if there is a closing block next then we don't need a ;
			return true;
		}
		return false;
	}

	function compressList($items, $delim) {
		if (count($items) == 1) return $items[0];	
		else return array('list', $delim, $items);
	}

	function compileBlock($rtags, $env) {
		// don't render functions
		foreach ($rtags as $i => $tag) {
			if (preg_match('/( |^)%/', $tag))
				unset($rtags[$i]);
		}
		if (empty($rtags)) return '';

		$props = 0;
		// print all the visible properties
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

		if ($props == 0) return '';

		// do some formatting
		if ($props == 1) $list = ' '.trim($list).' ';
		return implode(", ", $rtags).' {'.($props  > 1 ? "\n" : '').
			$list."}\n";

	}

	function compileProperty($name, $value, $level = 0) {
		// output all repeated properties
		foreach ($value as $v)
			$props[] = str_repeat('  ', $level).
				$name.':'.$this->compileValue($v).';';

		return implode("\n", $props);
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
		case 'string':
			// search for inline variables to replace
			$replace = array();
			if (preg_match_all('/{(@[\w-_][0-9\w-_]*)}/', $value[1], $m)) {
				foreach($m[1] as $name) {
					if (!isset($replace[$name]))
						$replace[$name] = $this->compileValue(array('variable', $name));
				}
			}
			foreach ($replace as $var=>$val) {
				// strip quotes
				if (preg_match('/^(["\']).*?(\1)$/', $val)) {
					$val = substr($val, 1, -1);
				}
				$value[1] = str_replace('{'.$var.'}', $val, $value[1]);
			}

			return $value[1];
		case 'color':
			if (count($value) == 5) { // rgba
				return 'rgba('.$value[1].','.$value[2].','.$value[3].','.$value[4].')';
			}

			$out = '#';
			foreach (range(1,3) as $i)
				$out .= ($value[$i] < 16 ? '0' : '').dechex($value[$i]);
			return $out;
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
	function fixColor($c) {
		foreach (range(1, 3) as $i) {
			if ($c[$i] < 0) $c[$i] = 0;
			if ($c[$i] > 255) $c[$i] = 255;
			$c[$i] = floor($c[$i]);
		}

		return $c;
	}

	function op_number_color($op, $lft, $rgt) {
		if ($op == '+' || $op = '*') {
			return $this->op_color_number($op, $rgt, $lft);
		}
	}

	function op_color_number($op, $lft, $rgt) {
		if ($rgt[0] == '%') $rgt[1] /= 100;

		return $this->op_color_color($op, $lft,
			array('color', $rgt[1], $rgt[1], $rgt[1]));
	}

	function op_color_color($op, $lft, $rgt) {
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
	function push() {
		$this->level++;
		$this->env[] = array();
	}

	// pop environment off the stack
	function pop() {
		if ($this->level == 1)
			throw new exception('parse error: unexpected end of block');

		$this->level--;
		return array_pop($this->env);
	}

	// set something in the current env
	function set($name, $value) {
		$this->env[count($this->env) - 1][$name] = $value;
	}

	// append to array in the current env
	function append($name, $value) {
		$this->env[count($this->env) - 1][$name][] = $value;
	}

	// put on the front of the value
	function prepend($name, $value) {
		if (isset($this->env[count($this->env) - 1][$name]))
			array_unshift($this->env[count($this->env) - 1][$name], $value);
		else $this->append($name, $value);
	}

	// get the highest occurrence of value
	function get($name, $env = null) {
		if (empty($env)) $env = $this->env;

		for ($i = count($env) - 1; $i >= 0; $i--)
			if (isset($env[$i][$name])) return $env[$i][$name];

		return null;
	}

	// get the most recent value of a variable
	// return default if it isn't found
	// $skip is number of vars to skip
	function getVal($name, $skip = 0, $default = array('keyword', '')) {
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
	function merge($name, $value) {
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
		// this is here mainly prevent notice from { } string accessor 
		if ($this->count >= strlen($this->buffer)) return false;

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

	function preg_quote($what) {
		return preg_quote($what, '/');
	}

	// match to $what, doesn't include in output
	function to($what, &$out) {
		if (!$this->match('(.*?)'.$this->preg_quote($what), $m)) return false;
		$out = $m[1];
		return true;
	}
	
	// try to match something on head of buffer
	function match($regex, &$out, $eatWhitespace = true) {
		$r = '/'.$regex.($eatWhitespace ? '\s*' : '').'/Ais';
		if (preg_match($r, $this->buffer, $out, null, $this->count)) {
			$this->count += strlen($out[0]);
			return true;
		}
		return false;
	}


	// match something without consuming it
	function peek($regex, &$out = null) {
		$r = '/'.$regex.'/Ais';
		$result =  preg_match($r, $this->buffer, $out, null, $this->count);
		
		return $result;
	}

	// seek to a spot in the buffer or return where we are on no argument
	function seek($where = null) {
		if ($where === null) return $this->count;
		else $this->count = $where;
		return true;
	}

	// parse and compile buffer
	function parse($str = null) {
		if ($str) $this->buffer = $str;		

		$this->env = array();
		$this->expandStack = array();
		$this->count = 0;
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
			$line = $this->line + substr_count(substr($this->buffer, 0, $this->count), "\n");
			if ($this->peek("(.*?)\n", $m))
				throw new exception('parse error: failed at `'.$m[1].'` line: '.$line);
		}

		if (count($this->env) > 1)
			throw new exception('failed to parse: unclosed block');


		// print_r($this->env);
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
	// todo: make it not mess up line counter with block comments
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

		return $out;
	}


	// compile to $in to $out if $in is newer than $out
	// returns true when it compiles, false otherwise
	public static function ccompile($in, $out) {
		if (!is_file($out) || filemtime($in) > filemtime($out)) {
			$less = new lessc($in);
			file_put_contents($out, $less->parse());
			return true;
		}

		return false;
	}

}



?>
