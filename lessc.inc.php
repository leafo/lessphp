<?php

/**
 * lessphp v0.2.1
 * http://leafo.net/lessphp
 *
 * LESS css compiler, adapted from http://lesscss.org/docs.html
 *
 * Copyright 2010, Leaf Corcoran <leafot@gmail.com>
 * Licensed under MIT or GPLv3, see LICENSE
 */


/**
 * The less compiler and parser.
 *
 * Converting LESS to CSS is a two stage process. First the incoming document
 * must be parsed. Parsing creates a tree in memory that represents the 
 * structure of the document. Then, the tree of the document is recursively
 * compiled into the CSS text. The compile step has an implicit step called
 * reduction, where values are brought to their lowest form before being
 * turned to text, eg. mathematical equations are solved, and variables are
 * dereferenced.
 *
 * The parsing stage produces the final structure of the document, for this
 * reason mixins are mixed in and attribute accessors are referenced during
 * the parse step. A reduction is done on the mixed in block as it is mixed in.
 *
 *  See the following:
 *    - entry point for parsing and compiling: lessc::parse()
 *    - parsing: lessc::parseChunk()
 *    - compiling: lessc::compileBlock()
 *
 */
class lessc {
	protected $buffer;
	protected $count;
	protected $line;

	public $indentLevel;
	public $indentChar = '  ';

	protected $env = null;

	protected $allParsedFiles = array();

	public $vPrefix = '@'; // prefix of abstract properties
	public $mPrefix = '$'; // prefix of abstract blocks
	public $imPrefix = '!'; // special character to add !important
	public $selfSelector = '&';

	static protected $precedence = array(
		'+' => 0,
		'-' => 0,
		'*' => 1,
		'/' => 1,
		'%' => 1,
	);
	static protected $operatorString; // regex string to match any of the operators

	// types that have delayed computation
	static protected $dtypes = array('expression', 'variable',
		'function', 'negative', 'list', 'lookup');

	/**
	 * @link http://www.w3.org/TR/css3-values/
	 */
	static protected $units=array(
		'em', 'ex', 'px', 'gd', 'rem', 'vw', 'vh', 'vm', 'ch', // Relative length units
		'in', 'cm', 'mm', 'pt', 'pc', // Absolute length units
		'%', // Percentages
		'deg', 'grad', 'rad', 'turn', // Angles
		'ms', 's', // Times
		'Hz', 'kHz', //Frequencies
	);
    
	public $importDisabled = false;
	public $importDir = '';

	public $compat = false; // lessjs compatibility mode, does nothing right now

	/**
	 * if we are in an expression then we don't need to worry about parsing font shorthand
	 * $inExp becomes true after the first value in an expression, or if we enter parens
	 */
	protected $inExp = false;

	/**
	 * if we are in parens we can be more liberal with whitespace around operators because 
	 * it must evaluate to a single value and thus is less ambiguous.
	 *
	 * Consider:
	 *     property1: 10 -5; // is two numbers, 10 and -5
	 *     property2: (10 -5); // should evaluate to 5
	 */
	protected $inParens = false;

	/**
	 * Parse a single chunk off the head of the buffer and place it.
	 * @return false when the buffer is empty, or there is an error
	 *
	 * This functions is called repeatedly until the entire document is
	 * parsed.
	 *
	 * This parser is most similar to a recursive descent parser. Single
	 * functions represent discrete grammatical rules for the language, and
	 * they are able to capture the text that represents those rules.
	 *
	 * Consider the function lessc::keyword(). (all parse functions are
	 * structured the same)
	 *
	 * The function takes a single reference argument. When calling the the
	 * function it will attempt to match a keyword on the head of the buffer.
	 * If it is successful, it will place the keyword in the referenced
	 * argument, advance the position in the buffer, and return true. If it
	 * fails then it won't advance the buffer and it will return false.
	 *
	 * All of these parse functions are powered by lessc::match(), which behaves
	 * the same way, but takes a literal regular expression. Sometimes it is
	 * more convenient to use match instead of creating a new function.
	 *
	 * Because of the format of the functions, to parse an entire string of
	 * grammatical rules, you can chain them together using &&.
	 *
	 * But, if some of the rules in the chain succeed before one fails, then
	 * then buffer position will be left at an invalid state. In order to 
	 * avoid this, lessc::seek() is used to remember and set buffer positions.
	 *
	 * Before doing a chain, use $s = $this->seek() to remember the current
	 * position into $s. Then if a chain fails, use $this->seek($s) to 
	 * go back where we started.
	 */
	function parseChunk() {
		if (empty($this->buffer)) return false;
		$s = $this->seek();
		
		// setting a property
		if ($this->keyword($key) && $this->assign() &&
			$this->propertyValue($value) && $this->end())
		{
			$this->append(array('assign', $key, $value));
			return true;
		} else {
			$this->seek($s);
		}

		// look for special css blocks
		if ($this->env->parent == null && $this->literal('@', false)) {
			$this->count--;

			// a font-face block
			if ($this->literal('@font-face') && $this->literal('{')) {
				$b = $this->pushSpecialBlock('@font-face');
				return true;
			} else {
				$this->seek($s);
			}

			// charset
			if ($this->literal('@charset') && $this->propertyValue($value) &&
				$this->end())
			{
				$this->append(array('charset', $value));
				return true;
			} else {
				$this->seek($s);
			}


			// media
			if ($this->literal('@media') && $this->mediaTypes($types, $rest) &&
				$this->literal('{'))
			{
				$b = $this->pushSpecialBlock('@media');
				$b->media = array($types, $rest);
				return true;
			} else {
				$this->seek($s);
			}

			// css animations
			if ($this->match('(@(-[a-z]+-)?keyframes)', $m) &&
				$this->propertyValue($value) && $this->literal('{'))
			{
				$b = $this->pushSpecialBlock(trim($m[0]));
				$b->keyframes = $value;
				return true;
			} else {
				$this->seek($s);
			}
		}

		if (isset($this->env->keyframes)) {
			if ($this->match("(to|from|[0-9]+%)", $m) && $this->literal('{')) {
				$this->pushSpecialBlock($m[1]);
				return true;
			} else {
				$this->seek($s);
			}
		}

		// setting a variable
		if ($this->variable($name) && $this->assign() &&
			$this->propertyValue($value) && $this->end())
		{
			$this->append(array('assign', $this->vPrefix.$name, $value));
			return true;
		} else {
			$this->seek($s);
		}

		if ($this->import($url, $media)) {
			// don't check .css files
			if (empty($media) && substr_compare($url, '.css', -4, 4) !== 0) {
				if ($this->importDisabled) {
					$this->append(array('raw', '/* import disabled */'));
				} else {
					$path = $this->findImport($url);
					if (!is_null($path)) {
						$this->append(array('import', $path));
						return true;
					}
				}
			}

			$this->append(array('raw', '@import url("'.$url.'")'.
				($media ? ' '.$media : '').';'));
			return true;
		}

		// opening parametric mixin
		if ($this->tag($tag, true) && $this->argumentDef($args) &&
			$this->literal('{'))
		{
			$block = $this->pushBlock($this->fixTags(array($tag)));
			$block->args = $args;
			return true;
		} else {
			$this->seek($s);
		}

		// opening a simple block
		if ($this->tags($tags) && $this->literal('{')) {
			$tags = $this->fixTags($tags);
			$this->pushBlock($tags);
			return true;
		} else {
			$this->seek($s);
		}

		// closing a block
		if ($this->literal('}')) {
			try {
				$block = $this->pop();
			} catch (exception $e) {
				$this->seek($s);
				$this->throwParseError($e->getMessage());
			}

			$hidden = true;
			if (!isset($block->args)) foreach ($block->tags as $tag) {
				if ($tag{0} != $this->mPrefix) {
					$hidden = false;
					break;
				}
			}

			if (!$hidden) $this->append(array('block', $block));
			foreach ($block->tags as $tag) {
				$this->env->children[$tag] = $block;
			}

			return true;
		}


		// mixin 
		if ($this->tags($tags, true, '>') &&
			($this->argumentValues($argv) || true) && $this->end())
		{
			$tags = $this->fixTags($tags);
			$this->append(array('mixin', $tags, $argv));
			return true;
		} else {
			$this->seek($s);
		}
		// spare ;
		if ($this->literal(';')) return true;

		return false; // got nothing, throw error
	}

	function fixTags($tags) {
		// move @ tags out of variable namespace
		foreach ($tags as &$tag) {
			if ($tag{0} == $this->vPrefix) $tag[0] = $this->mPrefix;
		}
		return $tags;
	}

	// attempts to find the path of an import url, returns null for css files
	function findImport($url) {
		foreach ((array)$this->importDir as $dir) {
			$full = $dir.(substr($dir, -1) != '/' ? '/' : '').$url;
			if ($this->fileExists($file = $full.'.less') || $this->fileExists($file = $full)) {
				return $file;
			}
		}

		return null;
	}

	function fileExists($name) {
		// sym link workaround
		return file_exists($name) || file_exists(realpath(preg_replace('/\w+\/\.\.\//', '', $name)));
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

	/**
	 * Attempt to consume an expression.
	 * @link http://en.wikipedia.org/wiki/Operator-precedence_parser#Pseudo-code
	 */
	function expression(&$out) {
		$s = $this->seek();
		if ($this->literal('(') && ($this->inExp = $this->inParens = true) && $this->expression($exp) && $this->literal(')')) {
			$lhs = $exp;
		} elseif ($this->seek($s) && $this->value($val)) {
			$lhs = $val;
		} else {
			$this->inParens = $this->inExp = false;
			$this->seek($s);
			return false;
		}

		$out = $this->expHelper($lhs, 0);
		$this->inParens = $this->inExp = false;
		return true;
	}

	/**
	 * recursively parse infix equation with $lhs at precedence $minP
	 */
	function expHelper($lhs, $minP) {
		$this->inExp = true;
		$ss = $this->seek();

		// if the if there was whitespace before the operator, then we require whitespace after
		// the operator for it to be a mathematical operator.

		$needWhite = false;
		if (!$this->inParens && preg_match('/\s/', $this->buffer{$this->count - 1})) {
			$needWhite = true;
		}

		// try to find a valid operator
		while ($this->match(self::$operatorString.($needWhite ? '\s' : ''), $m) && self::$precedence[$m[1]] >= $minP) {
			// get rhs
			$s = $this->seek();
			$p = $this->inParens;
			if ($this->literal('(') && ($this->inParens = true) && $this->expression($exp) && $this->literal(')')) {
				$this->inParens = $p;
				$rhs = $exp;
			} else {
				$this->inParens = $p;
				if ($this->seek($s) && $this->value($val)) {
					$rhs = $val;
				} else {
					break;
				}
			}

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

			$needWhite = false;
			if (!$this->inParens && preg_match('/\s/', $this->buffer{$this->count - 1})) {
				$needWhite = true;
			}
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
		// try a unit
		if ($this->unit($value)) return true;	

		// see if there is a negation
		$s = $this->seek();
		if ($this->literal('-', false) && $this->variable($vname)) {
			$value = array('negative', array('variable', $this->vPrefix.$vname));
			return true;
		} else {
			$this->seek($s);
		}

		// accessor 
		// must be done before color
		// this needs negation too
		if ($this->accessor($a)) {
			$a[1] = $this->fixTags($a[1]);
			$value = $a;
			return true;
		}
		
		// color
		if ($this->color($value)) return true;

		// css function
		// must be done after color
		if ($this->func($value)) return true;

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
			$value = array('variable', $this->vPrefix.$vname);
			return true;
		}

		return false;
	}

	// an import statement
	function import(&$url, &$media) {
		$s = $this->seek();
		if (!$this->literal('@import')) return false;

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
		return $this->to(';', $media, false, true);
	}

	// a list of media types, very lenient
	function mediaTypes(&$types, &$rest) {
		$s = $this->seek();
		$types = array();
		while ($this->match('([^,{\s]+)', $m)) {
			$types[] = $m[1];
			if (!$this->literal(',')) break;
		}

		// get everything else
		if ($this->to('{', $rest, true, true)) {
			$rest = trim($rest);
		}

		return count($types) > 0;
	}

	// a scoped value accessor
	// .hello > @scope1 > @scope2['value'];
	function accessor(&$var) {
		$s = $this->seek();

		if (!$this->tags($scope, true, '>') || !$this->literal('[')) {
			$this->seek($s);
			return false;
		}

		// either it is a variable or a property
		// why is a property wrapped in quotes, who knows!
		if ($this->variable($name)) {
			$name = $this->vPrefix.$name;
		} elseif ($this->literal("'") && $this->keyword($name) && $this->literal("'")) {
			// .. $this->count is messed up if we wanted to test another access type
		} else {
			$this->seek($s);
			return false;
		}

		if (!$this->literal(']')) {
			$this->seek($s);
			return false;
		}

		$var = array('lookup', $scope, $name);
		return true;
	}

	// a string 
	function string(&$string, &$d = null) {
		$s = $this->seek();
		if ($this->literal('"', false)) {
			$delim = '"';
		} elseif ($this->literal("'", false)) {
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

	/**
	 * Consume a number and optionally a unit.
	 * Can also consume a font shorthand if it is a simple case.
	 * $allowed restricts the types that are matched.
	 */
	function unit(&$unit, $allowed = null) {
		$simpleCase = $allowed == null;
		if (!$allowed) $allowed = self::$units;

		if ($this->match('(-?[0-9]*(\.)?[0-9]+)('.implode('|', $allowed).')?', $m, !$simpleCase)) {
			if (!isset($m[3])) $m[3] = 'number';
			$unit = array($m[3], $m[1]);

			// check for size/height font unit.. should this even be here?
			if ($simpleCase) {
				$s = $this->seek();
				if (!$this->inExp && $this->literal('/', false) && $this->unit($right, self::$units)) {
					$unit = array('keyword', $this->compileValue($unit).'/'.$this->compileValue($right));
				} else {
					// get rid of whitespace
					$this->seek($s);
					$this->match('', $_);
				}
			}

			return true;
		}

		return false;
	}

	// a # color
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
			foreach (array(3,2,1) as $i) {
				$t = $num % $width;
				$num /= $width;

				$color[$i] = $t * (256/$width) + $t * floor(16/$width);
			}
			
			$out = $color;
			return true;
		} 

		return false;
	}

	// consume a list of property values delimited by ; and wrapped in ()
	function argumentValues(&$args, $delim = ';') {
		$s = $this->seek();
		if (!$this->literal('(')) return false;

		$values = array();
		while (true) {
			if ($this->propertyValue($value)) $values[] = $value;
			if (!$this->literal($delim)) break;
			else {
				if ($value == null) $values[] = null;
				$value = null;
			}
		}	

		if (!$this->literal(')')) {
			$this->seek($s);
			return false;
		}
		
		$args = $values;
		return true;
	}

	// consume an argument definition list surrounded by ()
	// each argument is a variable name with optional value
	function argumentDef(&$args, $delim = ';') {
		$s = $this->seek();
		if (!$this->literal('(')) return false;

		$values = array();
		while ($this->variable($vname)) {
			$arg = array($vname);
			if ($this->assign() && $this->propertyValue($value)) {
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
	// this accepts a hanging delimiter
	function tags(&$tags, $simple = false, $delim = ',') {
		$tags = array();
		while ($this->tag($tt, $simple)) {
			$tags[] = $tt;
			if (!$this->literal($delim)) break;
		}
		if (count($tags) == 0) return false;

		return true;
	}

	// a bracketed value (contained within in a tag definition)
	function tagBracket(&$value) {
		$s = $this->seek();
		if ($this->literal('[') && $this->to(']', $c, true) && $this->literal(']', false)) {
			$value = '['.$c.']';
			// whitespace?
			if ($this->match('', $_)) $value .= $_[0];
			return true;
		}

		$this->seek($s);
		return false;
	}

	// a single tag
	function tag(&$tag, $simple = false) {
		if ($simple)
			$chars = '^,:;{}\][>\(\) ';
		else
			$chars = '^,;{}[';

		$tag = '';
		while ($this->tagBracket($first)) $tag .= $first;
		while ($this->match('(['.$chars.'0-9]['.$chars.']*)', $m)) {
			$tag .= $m[1];
			if ($simple) break;

			while ($this->tagBracket($brack)) $tag .= $brack;
		}
		$tag = trim($tag);
		if ($tag == '') return false;

		return true;
	}

	// a css function
	function func(&$func) {
		$s = $this->seek();

		if ($this->match('([\w\-_][\w\-_:\.]*)', $m) && $this->literal('(')) {
			$fname = $m[1];
			if ($fname == 'url') {
				$this->to(')', $content, true);
				$args = array('string', $content);
			} else {
				$args = array();
				while (true) {
					$ss = $this->seek();
					if ($this->keyword($name) && $this->literal('=') && $this->expressionList($value)) {
						$args[] = array('list', '=', array(array('keyword', $name), $value));
					} else {
						$this->seek($ss);
						if ($this->expressionList($value)) {
							$args[] = $value;
						}
					}

					if (!$this->literal(',')) break;
				}
				$args = array('list', ',', $args);
			}

			if ($this->literal(')')) {
				$func = array('function', $fname, $args);
				return true;
			}
		}

		$this->seek($s);
		return false;
	}

	// consume a less variable
	function variable(&$name) {
		$s = $this->seek();
		if ($this->literal($this->vPrefix, false) && $this->keyword($name)) {
			return true;	
		}

		return false;
	}

	/**
	 * Consume an assignment operator
	 * Can optionally take a name that will be set to the current property name
	 */
	function assign($name = null) {
		if ($name) $this->currentProperty = $name;
		return $this->literal(':') || $this->literal('=');
	}

	// consume a keyword
	function keyword(&$word) {
		if ($this->match('([\w_\-\*!"][\w\-_"]*)', $m)) {
			$word = $m[1];
			return true;
		}
		return false;
	}

	// consume an end of statement delimiter
	function end() {
		if ($this->literal(';'))
			return true;
		elseif ($this->count == strlen($this->buffer) || $this->buffer{$this->count} == '}') {
			// if there is end of file or a closing block next then we don't need a ;
			return true;
		}
		return false;
	}

	function compressList($items, $delim) {
		if (count($items) == 1) return $items[0];	
		else return array('list', $delim, $items);
	}

	/**
	 * Recursively compiles a block. 
	 * @param $block the block
	 * @param $parentTags the tags of the block that contained this one
	 *
	 * A block is analogous to a CSS block in most cases. A single less document
	 * is encapsulated in a block when parsed, but it does not have parent tags
	 * so all of it's children appear on the root level when compiled.
	 *
	 * Blocks are made up of props and children.
	 *
	 * Props are property instructions, array tuples which describe an action
	 * to be taken, eg. write a property, set a variable, mixin a block.
	 *
	 * The children of a block are just all the blocks that are defined within.
	 *
	 * Compiling the block involves pushing a fresh environment on the stack,
	 * and iterating through the props, compiling each one.
	 *
	 * See lessc::compileProp()
	 *
	 */
	function compileBlock($block, $parent_tags = null) {
		$isRoot = $parent_tags == null && $block->tags == null;

		$indent = str_repeat($this->indentChar, $this->indentLevel);

		if (!empty($block->no_multiply)) {
			$special_block = true;
			$this->indentLevel++;
			$tags = array();
		} else {
			$special_block = false;
			$tags = $this->multiplyTags($parent_tags, $block->tags);
		}

		$this->pushEnv();
		$lines = array();
		$blocks = array();
		foreach ($block->props as $prop) {
			$this->compileProp($prop, $block, $tags, $lines, $blocks);
		}

		$this->pop();

		$nl = $isRoot ? "\n".$indent :
			"\n".$indent.$this->indentChar;

		ob_start();

		if ($special_block) {
			$this->indentLevel--;
			if (isset($block->media)) {
				list($media_types, $media_rest) = $block->media;
				echo "@media ".join(', ', $media_types).
					(!empty($media_rest) ? " $media_rest" : '' );
			} elseif (isset($block->keyframes)) {
				echo $block->tags[0]." ".
					$this->compileValue($this->reduce($block->keyframes));
			} else {
				list($name) = $block->tags;
				echo $indent.$name;
			}

			echo ' {'.(count($lines) > 0 ? $nl : "\n");
		}

		// dump it
		if (count($lines) > 0) {
			if (!$special_block && !$isRoot) {
				echo $indent.implode(", ", $tags);
				if (count($lines) > 1) echo " {".$nl;
				else echo " { ";
			}

			echo implode($nl, $lines);

			if (!$special_block && !$isRoot) {
				if (count($lines) > 1) echo "\n".$indent."}\n";
				else echo " }\n";
			} else echo "\n";
		}

		foreach ($blocks as $b) echo $b;

		if ($special_block) {
			echo $indent."}\n";
		}

		return ob_get_clean();
	}


	// find the fully qualified tags for a block and its parent's tags
	function multiplyTags($parents, $current) {
		if ($parents == null) return $current;

		$tags = array();
		foreach ($parents as $ptag) {
			foreach ($current as $tag) {
				$tags[] = trim($ptag.
					($tag{0} == $this->selfSelector || $tag{0} == ':'
						? ltrim($tag, $this->selfSelector) : ' '.$tag));
			}
		}

		return $tags;
	}

	// attempt to find block pointed at by path within search_in or its parent
	function findBlock($search_in, $path) {
		if ($search_in == null) return null;
		$name = $path[0];

		if (isset($search_in->children[$name])) {
			$block = $search_in->children[$name];
			if (count($path) == 1) {
				return $block;
			} else {
				return $this->findBlock($block, array_slice($path, 1));
			}
		} else {
			return $this->findBlock($search_in->parent, $path);
		}
	}

	// sets all argument names in $args to either the default value
	// or the one passed in through $values
	function zipSetArgs($args, $values) {
		$i = 0;
		foreach ($args as $a) {
			if ($i < count($values) && !is_null($values[$i])) {
				$value = $values[$i];
			} elseif (isset($a[1])) {
				$value = $a[1];
			} else $value = null;

			$this->set($this->vPrefix.$a[0], $this->reduce($value));
			$i++;
		}
	}

	// compile a prop and update $lines or $blocks appropriately
	function compileProp($prop, $block, $tags, &$_lines, &$_blocks) {
		switch ($prop[0]) {
		case 'assign':
			list(, $name, $value) = $prop;
			if ($name[0] == $this->vPrefix) {
				$this->set($name, $this->reduce($value));
			} else {
				$_lines[] = "$name:".
					$this->compileValue($this->reduce($value)).";";
			}
			break;
		case 'block':
			list(, $child) = $prop;
			$_blocks[] = $this->compileBlock($child, $tags);
			break;
		case 'mixin':
			list(, $path, $args) = $prop;

			$mixin = $this->findBlock($block, $path);
			if (is_null($mixin)) {
				// echo "failed to find block: ".implode(" > ", $path)."\n";
				break; // throw error here??
			}

			$have_args = false;
			if (isset($mixin->args)) {
				$have_args = true;
				$this->pushEnv();
				$this->zipSetArgs($mixin->args, $args);
			}

			list($name) = $mixin->tags;
			if ($name == "div") {
				print_r($mixin->props);
			}

			$old_parent = $mixin->parent;
			$mixin->parent = $block;

			foreach ($mixin->props as $sub_prop) {
				$this->compileProp($sub_prop, $mixin, $tags, $_lines, $_blocks);
			}

			$mixin->parent = $old_parent;

			if ($have_args) $this->pop();

			break;
		case 'raw':
			$_lines[] = $prop[1];
			break;
		case 'import':
			list(, $path) = $prop;
			$this->addParsedFile($path);
			$root = $this->createChild($path)->parseTree();

			$root->parent = $block;
			foreach ($root->props as $sub_prop) {
				$this->compileProp($sub_prop, $root, $tags, $_lines, $_blocks);
			}

			// inject imported blocks into this block, local will overwrite import
			$block->children = array_merge($root->children, $block->children);
			break;
		case 'charset':
			list(, $value) = $prop;
			$_lines[] = '@charset '.$this->compileValue($this->reduce($value)).';';
			break;
		default:
			echo "unknown op: {$prop[0]}\n";
			throw new exception();
		}
	}


	/**
	 * Compiles a primitive value into a CSS property value.
	 *
	 * Values in lessphp are typed by being wrapped in arrays, their format is
	 * typically:
	 *
	 *     array(type, contents [, additional_contents]*)
	 *
	 * Will not work on non reduced values (expressions, variables, etc)
	 */
	function compileValue($value) {
		switch ($value[0]) {
		case 'list':
			// [1] - delimiter
			// [2] - array of values
			return implode($value[1], array_map(array($this, 'compileValue'), $value[2]));
		case 'keyword':
			// [1] - the keyword 
		case 'number':
			// [1] - the number 
			return $value[1];
		case 'string':
			// [1] - contents of string (includes quotes)
			
			// search for inline variables to replace
			$replace = array();
			if (preg_match_all('/{('.$this->preg_quote($this->vPrefix).'[\w-_][0-9\w-_]*?)}/', $value[1], $m)) {
				foreach ($m[1] as $name) {
					if (!isset($replace[$name]))
						$replace[$name] = $this->compileValue($this->reduce(array('variable', $name)));
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
			// [1] - red component (either number for a %)
			// [2] - green component
			// [3] - blue component
			// [4] - optional alpha component
			if (count($value) == 5) { // rgba
				return 'rgba('.$value[1].','.$value[2].','.$value[3].','.$value[4].')';
			}
			return sprintf("#%02x%02x%02x", $value[1], $value[2], $value[3]);
		case 'function':
			// [1] - function name
			// [2] - some value representing arguments

			// see if function evaluates to something else
			$value = $this->reduce($value);
			if ($value[0] == 'function') {
				return $value[1].'('.$this->compileValue($value[2]).')';
			}
			else return $this->compileValue($value);
		default: // assumed to be unit	
			return $value[1].$value[0];
		}
	}

	function lib_rgbahex($color) {
		if ($color[0] != 'color')
			throw new exception("color expected for rgbahex");

		return sprintf("#%02x%02x%02x%02x",
			isset($color[4]) ? $color[4]*255 : 0,
			$color[1],$color[2], $color[3]);
	}

	function lib_quote($arg) {
		return '"'.$this->compileValue($arg).'"';
	}

	function lib_unquote($arg) {
		$out = $this->compileValue($arg);
		if ($this->quoted($out)) $out = substr($out, 1, -1);
		return $out;
	}

	// alias for unquote
	function lib_e($arg) {
		return $this->lib_unquote($arg);
	}

	function lib_floor($arg) {
		return floor($arg[1]);
	}

	function lib_round($arg) {
		return round($arg[1]);
	}

	// is a string surrounded in quotes? returns the quoting char if true
	function quoted($s) {
		if (preg_match('/^("|\').*?\1$/', $s, $m))
			return $m[1];
		else return false;
	}

	/**
	 * Helper function to get argurments for color functions
	 * accepts invalid input, non colors interpreted to black
	 */
	function colorArgs($args) {
		if ($args[0] != 'list' || count($args[2]) < 2) {
			return array(array('color', 0, 0, 0));
		}
		list($color, $delta) = $args[2];
		if ($color[0] != 'color')
			$color = array('color', 0, 0, 0);

		$delta = floatval($delta[1]);

		return array($color, $delta);
	}

	function lib_darken($args) {
		list($color, $delta) = $this->colorArgs($args);

		$hsl = $this->toHSL($color);
		$hsl[3] = $this->clamp($hsl[3] - $delta, 100);
		return $this->toRGB($hsl);
	}

	function lib_lighten($args) {
		list($color, $delta) = $this->colorArgs($args);

		$hsl = $this->toHSL($color);
		$hsl[3] = $this->clamp($hsl[3] + $delta, 100);
		return $this->toRGB($hsl);
	}

	function lib_saturate($args) {
		list($color, $delta) = $this->colorArgs($args);

		$hsl = $this->toHSL($color);
		$hsl[2] = $this->clamp($hsl[2] + $delta, 100);
		return $this->toRGB($hsl);
	}

	function lib_desaturate($args) {
		list($color, $delta) = $this->colorArgs($args);

		$hsl = $this->toHSL($color);
		$hsl[2] = $this->clamp($hsl[2] - $delta, 100);
		return $this->toRGB($hsl);
	}

	function lib_spin($args) {
		list($color, $delta) = $this->colorArgs($args);

		$hsl = $this->toHSL($color);
		$hsl[1] = $this->clamp($hsl[1] + $delta, 360);
		return $this->toRGB($hsl);
	}

	function lib_fadeout($args) {
		list($color, $delta) = $this->colorArgs($args);
		$color[4] = $this->clamp((isset($color[4]) ? $color[4] : 1) - $delta/100);
		return $color;
	}

	function lib_fadein($args) {
		list($color, $delta) = $this->colorArgs($args);
		$color[4] = $this->clamp((isset($color[4]) ? $color[4] : 1) + $delta/100);
		return $color;
	}

	function lib_hue($color) {
		if ($color[0] != 'color') return 0;
		$hsl = $this->toHSL($color);
		return round($hsl[1]);
	}

	function lib_saturation($color) {
		if ($color[0] != 'color') return 0;
		$hsl = $this->toHSL($color);
		return round($hsl[2]);
	}

	function lib_lightness($color) {
		if ($color[0] != 'color') return 0;
		$hsl = $this->toHSL($color);
		return round($hsl[3]);
	}

	function toHSL($color) {
		if ($color[0] == 'hsl') return $color;

		$r = $color[1] / 255;
		$g = $color[2] / 255;
		$b = $color[3] / 255;

		$min = min($r, $g, $b);
		$max = max($r, $g, $b);

		$L = ($min + $max) / 2;
		if ($min == $max) {
			$S = $H = 0;
		} else {
			if ($L < 0.5)
				$S = ($max - $min)/($max + $min);
			else
				$S = ($max - $min)/(2.0 - $max - $min);

			if ($r == $max) $H = ($g - $b)/($max - $min);
			elseif ($g == $max) $H = 2.0 + ($b - $r)/($max - $min);
			elseif ($b == $max) $H = 4.0 + ($r - $g)/($max - $min);

		}

		$out = array('hsl',
			($H < 0 ? $H + 6 : $H)*60,
			$S*100,
			$L*100,
		);

		if (count($color) > 4) $out[] = $color[4]; // copy alpha
		return $out;
	}

	function toRGB_helper($comp, $temp1, $temp2) {
		if ($comp < 0) $comp += 1.0;
		elseif ($comp > 1) $comp -= 1.0;

		if (6 * $comp < 1) return $temp1 + ($temp2 - $temp1) * 6 * $comp;
		if (2 * $comp < 1) return $temp2;
		if (3 * $comp < 2) return $temp1 + ($temp2 - $temp1)*((2/3) - $comp) * 6;

		return $temp1;
	}

	/**
	 * Converts an hsl array into a color value in rgb.
	 * Expects H to be in range of 0 to 360, S and L in 0 to 100
	 */
	function toRGB($color) {
		if ($color == 'color') return $color;

		$H = $color[1] / 360;
		$S = $color[2] / 100;
		$L = $color[3] / 100;

		if ($S == 0) {
			$r = $g = $b = $L;
		} else {
			$temp2 = $L < 0.5 ?
				$L*(1.0 + $S) :
				$L + $S - $L * $S;

			$temp1 = 2.0 * $L - $temp2;

			$r = $this->toRGB_helper($H + 1/3, $temp1, $temp2);
			$g = $this->toRGB_helper($H, $temp1, $temp2);
			$b = $this->toRGB_helper($H - 1/3, $temp1, $temp2);
		}

		$out = array('color', round($r*255), round($g*255), round($b*255));
		if (count($color) > 4) $out[] = $color[4]; // copy alpha
		return $out;
	}

	function clamp($v, $max = 1, $min = 0) {
		return min($max, max($min, $v));
	}

	/**
	 * Convert the rgb, rgba, hsl color literals of function type
	 * as returned by the parser into values of color type.
	 */
	function funcToColor($func) {
		$fname = $func[1];
		if ($func[2][0] != 'list') return false; // need a list of arguments
		$rawComponents = $func[2][2];

		if ($fname == 'hsl' || $fname == 'hsla') {
			$hsl = array('hsl');
			$i = 0;
			foreach ($rawComponents as $c) {
				$val = $this->reduce($c);
				$val = isset($val[1]) ? floatval($val[1]) : 0;

				if ($i == 0) $clamp = 360;
				elseif ($i < 4) $clamp = 100;
				else $clamp = 1;

				$hsl[] = $this->clamp($val, $clamp);
				$i++;
			}

			while (count($hsl) < 4) $hsl[] = 0;
			return $this->toRGB($hsl);

		} elseif ($fname == 'rgb' || $fname == 'rgba') {
			$components = array();
			$i = 1;
			foreach	($rawComponents as $c) {
				$c = $this->reduce($c);
				if ($i < 4) {
					if ($c[0] == '%') $components[] = 255 * ($c[1] / 100);
					else $components[] = floatval($c[1]); 
				} elseif ($i == 4) {
					if ($c[0] == '%') $components[] = 1.0 * ($c[1] / 100);
					else $components[] = floatval($c[1]);
				} else break;

				$i++;
			}
			while (count($components) < 3) $components[] = 0;
			array_unshift($components, 'color');
			return $this->fixColor($components);
		}

		return false;
	}

	// reduce a delayed type to its final value
	// dereference variables and solve equations
	function reduce($var, $defaultValue = array('number', 0)) {
		while (in_array($var[0], self::$dtypes)) {
			if ($var[0] == 'list') {
				foreach ($var[2] as &$value) $value = $this->reduce($value);
				break;
			} elseif ($var[0] == 'expression') {
				$var = $this->evaluate($var[1], $var[2], $var[3]);
			} elseif ($var[0] == 'variable') {
				$var = $this->get($var[1]);
			} elseif ($var[0] == 'lookup') {
				// do accessor here....
				$var = array('number', 0);
			} elseif ($var[0] == 'function') {
				$color = $this->funcToColor($var);
				if ($color) $var = $color;
				else {
					list($_, $name, $args) = $var;
					$f = array($this, 'lib_'.$name);
					if (is_callable($f)) {
						if ($args[0] == 'list')
							$args = $this->compressList($args[2], $args[1]);

						$var = call_user_func($f, $this->reduce($args));

						// convet to a typed value if the result is a php primitive
						if (is_numeric($var)) $var = array('number', $var);
						elseif (!is_array($var)) $var = array('keyword', $var);
					} else {
						// plain function, reduce args
						$var[2] = $this->reduce($var[2]);
					}
				}
				break; // done reducing after a function
			} elseif ($var[0] == 'negative') {
				$value = $this->reduce($var[1]);
				if (is_numeric($value[1])) {
					$value[1] = -1*$value[1];
				} 
				$var = $value;
			}
		}

		return $var;
	}

	// evaluate an expression
	function evaluate($op, $left, $right) {
		$left = $this->reduce($left);
		$right = $this->reduce($right);

		if ($left[0] == 'color' && $right[0] == 'color') {
			$out = $this->op_color_color($op, $left, $right);
			return $out;
		}

		if ($left[0] == 'color') {
			return $this->op_color_number($op, $left, $right);
		}

		if ($right[0] == 'color') {
			return $this->op_number_color($op, $left, $right);
		}

		// concatenate strings
		if ($op == '+' && $left[0] == 'string') {
			$append = $this->compileValue($right);
			if ($this->quoted($append)) $append = substr($append, 1, -1);

			$lhs = $this->compileValue($left);
			if ($q = $this->quoted($lhs)) $lhs = substr($lhs, 1, -1);
			if (!$q) $q = '';

			return array('string', $q.$lhs.$append.$q);
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
			array_fill(1, count($lft) - 1, $rgt[1]));
	}

	function op_color_color($op, $left, $right) {
		$out = array('color');
		$max = count($left) > count($right) ? count($left) : count($right);
		foreach (range(1, $max - 1) as $i) {
			$lval = isset($left[$i]) ? $left[$i] : 0;
			$rval = isset($right[$i]) ? $right[$i] : 0;
			switch ($op) {
			case '+':
				$out[] = $lval + $rval;
				break;
			case '-':
				$out[] = $lval - $rval;
				break;
			case '*':
				$out[] = $lval * $rval;
				break;
			case '%':
				$out[] = $lval % $rval;
				break;
			case '/':
				if ($rval == 0) throw new exception("evaluate error: can't divide by zero");
				$out[] = $lval / $rval;
				break;
			default:
				throw new exception('evaluate error: color op number failed on op '.$op);
			}
		}
		return $this->fixColor($out);
	}

	// operator on two numbers
	function op_number_number($op, $left, $right) {
		if ($right[0] == '%') $right[1] /= 100;

		// figure out type
		if ($right[0] == 'number' || $right[0] == '%') $type = $left[0];
		else $type = $right[0];

		$value = 0;
		switch ($op) {
		case '+':
			$value = $left[1] + $right[1];
			break;	
		case '*':
			$value = $left[1] * $right[1];
			break;	
		case '-':
			$value = $left[1] - $right[1];
			break;	
		case '%':
			$value = $left[1] % $right[1];
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

	// push a new block on the stack, used for parsing
	function pushBlock($tags) {
		$b = new stdclass;
		$b->parent = $this->env;

		$b->tags = $tags;
		$b->props = array();
		$b->children = array();

		$this->env = $b;
		return $b;
	}
	
	// push a block that doesn't multiply tags
	function pushSpecialBlock($name) {
		$b = $this->pushBlock(array($name));
		$b->no_multiply = true;
		return $b;
	}

	// used for compiliation variable state
	function pushEnv() {
		$e = new stdclass;
		$e->parent = $this->env;

		$this->store = array();

		$this->env = $e;
		return $e;
	}

	// pop something off the stack
	function pop() {
		$old = $this->env;
		$this->env = $this->env->parent;
		return $old;
	}

	// set something in the current env
	function set($name, $value) {
		$this->env->store[$name] = $value;
	}

	// append an property
	function append($prop) {
		$this->env->props[] = $prop;
	}

	// get the highest occurrence entry for a name
	function get($name) {
		$current = $this->env;
		while ($current) {
			if (isset($current->store[$name]))
				return $current->store[$name];
			else
				$current = $current->parent;
		}

		return null;
	}
	
	/* raw parsing functions */

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

	// advance counter to next occurrence of $what
	// $until - don't include $what in advance
	function to($what, &$out, $until = false, $allowNewline = false) {
		$validChars = $allowNewline ? "[^\n]" : '.';
		if (!$this->match('('.$validChars.'*?)'.$this->preg_quote($what), $m, !$until)) return false;
		if ($until) $this->count -= strlen($what); // give back $what
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

	/**
	 * Initialize state for a fresh parse
	 */
	protected function prepareParser($buff) {
		$this->env = null;
		$this->expandStack = array();
		$this->indentLevel = 0;
		$this->count = 0;
		$this->line = 1;

		$this->buffer = $this->removeComments($buff);
		$this->pushBlock(null); // set up global scope

		// trim whitespace on head
		if (preg_match('/^\s+/', $this->buffer, $m)) {
			$this->line  += substr_count($m[0], "\n");
			$this->buffer = ltrim($this->buffer);
		}
	}

	// create a child parser (for compiling an import)
	protected function createChild($fname) {
		$less = new lessc($fname);
		$less->importDir = $this->importDir;
		$less->indentChar = $this->indentChar;
		$less->compat = $this->compat;
		return $less;
	}

	protected function parseTree($str = null) {
		$this->prepareParser(is_null($str) ? $this->buffer : $str);
		while (false !== $this->parseChunk());

		if ($this->count != strlen($this->buffer))
			$this->throwParseError();

		if (!is_null($this->env->parent))
			throw new exception('parse error: unclosed block');

		$root = $this->env;
		$this->env = null;
		return $root;
	}

	// inject array of unparsed strings into environment as variables
	protected function injectVariables($args) {
		$this->pushEnv();
		$parser = new lessc();
		foreach ($args as $name => $str_value) {
			if ($name{0} != '@') $name = '@'.$name;
			$parser->count = 0;
			$parser->buffer = (string)$str_value;
			if (!$parser->propertyValue($value)) {
				throw new Exception("failed to parse passed in variable $name: $str_value");
			}

			$this->set($name, $value);
		}
	}
	
	// parse and compile buffer
	function parse($str = null, $initial_variables = null) {
		$locale = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, "C");
		$root = $this->parseTree($str);

		if ($initial_variables) $this->injectVariables($initial_variables);
		$out = $this->compileBlock($root);
		setlocale(LC_NUMERIC, $locale);
		return $out;
	}

	function throwParseError($msg = 'parse error') {
		$line = $this->line + substr_count(substr($this->buffer, 0, $this->count), "\n");
		if (isset($this->fileName)) {
			$loc = $this->fileName.' on line '.$line;
		} else {
			$loc = "line: ".$line;
		}

		if ($this->peek("(.*?)(\n|$)", $m))
			throw new exception($msg.': failed at `'.$m[1].'` '.$loc);
	}

	/**
	 * Initialize any static state, can initialize parser for a file
	 */
	function __construct($fname = null, $opts = null) {
		if (!self::$operatorString) {
			self::$operatorString = 
				'('.implode('|', array_map(array($this, 'preg_quote'),
					array_keys(self::$precedence))).')';
		}

		if ($fname) {
			if (!is_file($fname)) {
				throw new Exception('load error: failed to find '.$fname);
			}
			$pi = pathinfo($fname);

			$this->fileName = $fname;
			$this->importDir = $pi['dirname'].'/';
			$this->buffer = file_get_contents($fname);

			$this->addParsedFile($fname);
		}
	}

	// remove comments from $text
	// todo: make it work for all functions, not just url
	function removeComments($text) {
		$look = array(
			'url(', '//', '/*', '"', "'"
		);

		$out = '';
		$min = null;
		$done = false;
		while (true) {
			// find the next item
			foreach ($look as $token) {
				$pos = strpos($text, $token);
				if ($pos !== false) {
					if (!isset($min) || $pos < $min[1]) $min = array($token, $pos);
				}
			}

			if (is_null($min)) break;

			$count = $min[1];
			$skip = 0;
			$newlines = 0;
			switch ($min[0]) {
			case 'url(':
				if (preg_match('/url\(.*?\)/', $text, $m, 0, $count))
					$count += strlen($m[0]) - strlen($min[0]);
				break;
			case '"':
			case "'":
				if (preg_match('/'.$min[0].'.*?'.$min[0].'/', $text, $m, 0, $count))
					$count += strlen($m[0]) - 1;
				break;
			case '//':
				$skip = strpos($text, "\n", $count);
				if ($skip === false) $skip = strlen($text) - $count;
				else $skip -= $count;
				break;
			case '/*': 
				if (preg_match('/\/\*.*?\*\//s', $text, $m, 0, $count)) {
					$skip = strlen($m[0]);
					$newlines = substr_count($m[0], "\n");
				}
				break;
			}

			if ($skip == 0) $count += strlen($min[0]);

			$out .= substr($text, 0, $count).str_repeat("\n", $newlines);
			$text = substr($text, $count + $skip);

			$min = null;
		}

		return $out.$text;
	}

	public function allParsedFiles() { return $this->allParsedFiles; }
	protected function addParsedFile($file) {
		$this->allParsedFiles[realpath($file)] = filemtime($file);
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

	/**
	 * Execute lessphp on a .less file or a lessphp cache structure
	 * 
	 * The lessphp cache structure contains information about a specific
	 * less file having been parsed. It can be used as a hint for future
	 * calls to determine whether or not a rebuild is required.
	 * 
	 * The cache structure contains two important keys that may be used
	 * externally:
	 * 
	 * compiled: The final compiled CSS
	 * updated: The time (in seconds) the CSS was last compiled
	 * 
	 * The cache structure is a plain-ol' PHP associative array and can
	 * be serialized and unserialized without a hitch.
	 * 
	 * @param mixed $in Input
	 * @param bool $force Force rebuild?
	 * @return array lessphp cache structure
	 */
	public static function cexecute($in, $force = false) {

		// assume no root
		$root = null;

		if (is_string($in)) {
			$root = $in;
		} elseif (is_array($in) and isset($in['root'])) {
			if ($force or ! isset($in['files'])) {
				// If we are forcing a recompile or if for some reason the
				// structure does not contain any file information we should
				// specify the root to trigger a rebuild.
				$root = $in['root'];
			} elseif (isset($in['files']) and is_array($in['files'])) {
				foreach ($in['files'] as $fname => $ftime ) {
					if (!file_exists($fname) or filemtime($fname) > $ftime) {
						// One of the files we knew about previously has changed
						// so we should look at our incoming root again.
						$root = $in['root'];
						break;
					}
				}
			}
		} else {
			// TODO: Throw an exception? We got neither a string nor something
			// that looks like a compatible lessphp cache structure.
			return null;
		}

		if ($root !== null) {
			// If we have a root value which means we should rebuild.
			$less = new lessc($root);
			$out = array();
			$out['root'] = $root;
			$out['compiled'] = $less->parse();
			$out['files'] = $less->allParsedFiles();
			$out['updated'] = time();
			return $out;
		} else {
			// No changes, pass back the structure
			// we were given initially.
			return $in;
		}

	}
}

