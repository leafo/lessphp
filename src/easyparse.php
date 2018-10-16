<?php

class easyparse {
    var $buffer;
    var $count;

    function __construct($str) {
        $this->count = 0;
        $this->buffer = trim($str);
    }

    function seek($where = null) {
        if ($where === null) return $this->count;
        else $this->count = $where;
        return true;
    }

    function preg_quote($what) {
        return preg_quote($what, '/');
    }

    function match($regex, &$out, $eatWhitespace = true) {
        $r = '/'.$regex.($eatWhitespace ? '\s*' : '').'/Ais';
        if (preg_match($r, $this->buffer, $out, null, $this->count)) {
            $this->count += strlen($out[0]);
            return true;
        }
        return false;
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

}
