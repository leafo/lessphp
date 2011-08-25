<?php

// well this is complicated

require_once "lex.inc.php";
require_once "lessc.inc.php";

class less_lex extends parallel_lex {
	var $opts = 'is';

	var $skip = '(?:[ \r\n\t]+|\/\/[^\n]*|\/\*.*?\*\/)';

	var $lit = array(
		'@charset',
		'@import',
		'@page',
		'@media',
		'{', '}',
		':', ';', '[', ']',
		'+', '-', '*', '%', '/',
		'(', ')', '!',
		'=', '~', '^', '$', '|',
		'>', '&', ','
	);
	var $exp = array(
		'word' => '[_a-z-][_a-z0-9-]+|[_a-z]',
		'unit' => '(?:[0-9]*\.)?[0-9]+[a-z%]+',
		'num' => '(?:[0-9]*\.)?[0-9]+',
		'string' => '"(?:\\\\"|[^"])*?"|\'[^\']*?\'',
		// 'string2' => '\'[^\']*?\'',
		'class' => '\.[_a-z][a-z0-9-]*',
		'variable' => '@[_a-z0-9-]+',
		'color' => '#(?:[0-9a-f]{3}|[0-9a-f]{6})',
		'id' => '#[_a-z][_a-z0-9-]*',
	);

	function __construct($input) {
		$this->exp['percent'] = $this->exp['num'].'%';
		parent::__construct($input);
		// echo $this->regex.PHP_EOL;
	}
}

class snapshot {
	function __construct($parent) {
		$this->parent = $parent;
		$this->pos = $parent->pos;
		$this->tokens = $parent->tokens;
	}

	function next_token() {
		if ($this->pos >= count($this->tokens))
			return less_lex::EOF;

		return $this->tokens[$this->pos++];
	}

	// advance the parent's position
	function accept() {
		$this->parent->pos = $this->pos;
	}

	function snap() {
		return new snapshot($this);
	}

	function show() {
		print_r(array_slice($this->tokens, $this->pos, 5));
	}
}

// a unit of parsing
class parslet {
	function __construct($parser, $name, $pattern=null) {
		$this->parser = $parser;
		$this->name = $name;
		$this->pattern = $pattern;
	}

	function get_stream($s=null) {
		return is_null($s) ? new snapshot($this->parser) : $s->snap();
	}

	function match_any($stream, $items) {
		$tmp_stream = $stream->snap();
		$next_token = $tmp_stream->next_token();
		foreach ($items as $item) {
			if ($item instanceof parslet) {
				$result = $item->parse($stream);
				if ($result !== false) {
					return $result;
				}
			} elseif (is_array($item)) {
				$result = $this->match_every($stream, $item);
				if ($result !== false) return $result;
			} else { // token match
				if ($item == $next_token[0]) {
					$tmp_stream->accept();
					return $next_token;
				}
			}
		}
		return false;
	}

	function match_every($stream, $items) {
		$accepted = array();
		foreach ($items as $item) {
			if ($item instanceof parslet) {
				$result = $item->parse($stream);
				if ($result === false) return false;
				$accepted[] = $result;
			} elseif (is_array($item)) {
				$result = $this->match_any($stream, $item);
				if ($result === false) return false;
				$accepted[] = $result;
			} else { // token match
				$next = $stream->next_token();
				if ($item != $next[0]) return false;
				$accepted[] = $next;
			}
		}
		return $accepted;
	}

	function parse($stream=null) {
		$stream = $this->get_stream($stream);
		$result = $this->match_every($stream, $this->pattern);
		if ($result !== false) {
			$stream->accept();
			return $this->dispatch($result);
		}
		return false;
	}

	function _list($delim=null, $dispatch=null) {
		return new parslet_rep($this, $delim, $dispatch);
	}

	// return a version of this parslet that matches but doesn't consume input
	function _noconsume() {
		return new parslet_noconsume($this);
	}

	// matches an ordered of all parslets
	function _or() {
		$items = func_get_args();
		array_unshift($items, $this);
		return new parslet_or($items);
		// todo: just use paslet instead of new one
		// return new parslet($this->parser, "ignore", array($items));
	}

	function _optional($missing_dispatch=null) {
		return new parslet_optional($this, $missing_dispatch);
	}

	function dispatch($accepted, $name=null) {
		$name = is_null($name) ? $this->name : $name;
		if ($name == "ignore") return $accepted;

		if (is_int($name)) {
			return $accepted[$this->name - 1];
		}

		$func_name = "node_".$name;
		$func = array($this->parser, $func_name);
		if (is_callable($func)) {
			return call_user_func($func, $accepted);
		}
		throw new exception("fatal error: unknown parse handler $func_name");
	}
}

class parslet_rep extends parslet {
	function __construct($plet, $delim, $dispatch=null) {
		parent::__construct($plet->parser, $plet->name."_list");
		$this->plet = $plet;
		if (!is_null($delim)) {
			$this->delim = $delim instanceof parslet ? $delim :
				$this->parser->p("ignore", $delim);
		} else {
			$this->delim = null;
		}
		$this->dispatch = $dispatch; // maybe safe to just put this in name?
	}

	function parse($stream=null) {
		$stream = $this->get_stream($stream);

		// we always eat extra delim right now, might not be good
		$results = array();
		while ($value = $this->plet->parse($stream)) {
			$results[] = $value;
			if (!is_null($this->delim)) {
				if (!$this->delim->parse($stream)) break;
			}
		}

		if (count($results) == 0) return false;

		$stream->accept();
		if (!is_null($this->dispatch)) {
			$results = $this->dispatch($results, $this->dispatch);
		}
		return $results;
	}
}

class parslet_noconsume extends parslet {
	function __construct($plet) {
		parent::__construct($plet->parser, $plet->name);
		$this->plet = $plet;
	}

	function parse($stream=null) {
		// never accept stream to not consume
		$stream = $this->get_stream($stream);
		return $this->plet->parse($stream);
	}
}

class parslet_or extends parslet {
	function __construct($items) {
		if (count($items) < 1) throw new exception("expecting more than 1 plet for or");
		parent::__construct($items[0]->parser, "or_items");
		$this->items = $items;
	}

	function parse($stream=null) {
		$stream = $this->get_stream($stream);
		foreach ($this->items as $item) {
			$result = $item->parse($stream);
			if ($result !== false) {
				$stream->accept();
				return $result;
			}
		}
		return false;
	}
}

// end of input
class parslet_end extends parslet {
	function __construct($parser) {
		$this->parser = $parser;
	}

	function parse($stream=null) {
		$stream = $this->get_stream($stream);
		return $stream->next_token() == less_lex::EOF;
	}
}

class parslet_optional extends parslet {
	function __construct($plet, $missing_dispatch=null) {
		parent::__construct($plet->parser, "optional_".$plet->name);
		$this->plet = $plet;
		$this->missing_dispatch = $missing_dispatch;
	}

	function parse($stream=null) {
		// $stream = $this->get_stream($stream);
		$result = $this->plet->parse($stream);
		if ($result === false) {
			$result = is_null($this->missing_dispatch) ? true :
				$this->dispatch($this->name, $this->missing_dispatch);
		}

		return $result;
	}
}

class less_parse {
	function __construct($tokens) {
		$this->pos = 0;
		$this->tokens = $tokens;

		$exp = $this->p("exp");
		$parens	= $this->p("parens", "(", $exp, ")");

		// need accessor, function, and negation
		$value = $this->p("value", array("string", "num",
			"unit", "word", "variable", "color"));

		$value_list = $value->_list(null, "value_list");
		$property_value = $value_list->_list(",", "property_value");

		// accept color here and convert to id
		$tag_id = $this->p("tag_id", array("color", "id"));
		$simple_tag = $this->p("simple_tag", array("word", "class", $tag_id));

		$arg_def = $this->p("arg_def", "variable",
			$this->p(2, ":", $value_list)->_optional());

		$arg_def = $arg_def->_list(array(",", ";"))->_optional("default");

		$mixin_name = "class";
		$mixin_func = $this->p("mixin_func_decl", $mixin_name, "(", $arg_def, ")");

		$tags = $this->p(1, array($mixin_func, $simple_tag->_list(',', 'wrap_tags')));

		$terminator = $this->p(1, ";");
		$inner_end = $this->p(1, array(
			$terminator,
			$this->p(1, "}")->_noconsume()
		));

		$assign = $this->p("assign", array("word", "variable"), ":", $property_value);
		$block_assign = $this->p(1, $assign, $inner_end);

		$arg_values = $value_list->_list(array(',', ';'))->_optional("default");
		$mixin_invoke_args = $this->p(2, "(", $arg_values, ")")->_optional("default");

		$mixin_invoke = $this->p("mixin_invoke", $mixin_name, $mixin_invoke_args, $inner_end);

		$outer_end = $terminator->_or(new parslet_end($this));
		$root_assign = $this->p(1, $assign, $outer_end);

		$block = $this->p("block");

		$block_entry = $this->p(1, array(
			$block_assign,
			$mixin_invoke,
			$block,
		));

		$block_inner = $block_entry->_list()->_optional("default");

		$block = $this->p($block,
			$tags, "{", $block_inner, "}");

		$root = $this->p("root", $root_assign->_or($block)->_list());

		// print_r($root->parse());

		$root_node = $this->link_block($root->parse());
		$less = new lessc();
		echo $less->compile($root_node);

		// print_r($assign->parse());
		// print_r($block->parse());

		// $s = new snapshot($this);
		// $s->show();
	}

	function link_block($block, $parent=null) {
		$block->parent = $parent;
		$filtered_props = array();
		$block->children = array();
		foreach ($block->props as $prop) {
			if ($prop[0] == "block") {
				$child = $prop[1];
				foreach ($child->tags as $tag) {
					$block->children[$tag] = $child;
				}
				$this->link_block($child, $block);
				if (isset($child->args)) continue;
			}
			$filtered_props[] = $prop;
		}
		$block->props = $filtered_props;
		return $block;
	}

	function node_value($toks) {
		$value = $toks[0];
		switch($value[0]) {
		// todo: rename these in compiler
		case "num":
			$value[0] = "number";
			break;
		case "word":
			$value[0] = "keyword";
			break;

		case "unit":
			if (preg_match('/^([0-9]+)(.+)$/', $value[1], $m)) {
				$value = array($m[2], $m[1]);
			}
			break;
		case "color":
			$num = substr($value[1], 1);
			if (strlen($num) == 3) {
				$width = 16;
			} else {
				$width = 256;
			}

			$value = array("color", 0,0,0);
			$num = hexdec($num);
			for ($i = 3; $i > 0; $i--) {
				$t = $num % $width;
				$num /= $width;
				$value[$i] = $t * (256/$width) + $t * floor(16/$width);
			}
			
			break;
		}

		return $value;
	}

	function node_assign($toks) {
		list($name, $_, $value) = $toks;
		return array("assign", $name[1], $value);
	}

	function node_tag_id($tok) {
		$id = $tok[0];
		$id[0] = "id";
		return $id;
	}

	function node_simple_tag($tok) {
		return $tok[0][1];
	}

	function node_value_list($values) {
		if (count($values) == 1) return $values[0];
		return array("list", " ", $values);
	}

	function node_property_value($values) {
		if (count($values) == 1) return $values[0];
		return array("list", ",", $values);
	}

	function node_mixin_func_decl($toks) {
		return array("mixin_func", $toks[0][1], $toks[2]);
	}

	function node_wrap_tags($tags) {
		return array("tags", $tags);
	}

	function node_arg_def($tok) {
		$out = array(substr($tok[0][1], 1)); // strip @
		if ($tok[1] !== true) { // this is how we handle optional
			$out[] = $tok[1];
		}
		return $out;
	}

	function node_mixin_invoke($toks) {
		return array("mixin", array($toks[0][1]), $toks[1]);
	}

	function node_block($toks) {
		list($tags, $_, $body, $_) = $toks;
		$block = new stdclass;
		if ($tags[0] == "mixin_func") {
			$block->tags = array($tags[1]);
			$block->args = $tags[2];
		} else {
			$block->tags = $tags[1];
		}

		$block->props = $body;
		return array("block", $block);
	}

	function node_root($toks) {
		$root = new stdclass;
		$root->tags = $root->parent = null;
		$root->props = $toks[0];
		return $root;
	}

	function node_default() {
		return array();
	}

	function p($name) {
		$patt = func_get_args();
		array_shift($patt);
		if ($name instanceof parslet) {
			$name->pattern = $patt;
			return $name;
		}
		return new parslet($this, $name, $patt);
	}
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

