<?php

class lessc_formatter_compressed extends lessc_formatter_classic {
    public $disableSingle = true;
    public $open = "{";
    public $selectorSeparator = ",";
    public $assignSeparator = ":";
    public $break = "";
    public $compressColors = true;

    public function indentStr($n = 0) {
        return "";
    }
}
