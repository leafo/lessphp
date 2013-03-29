<?php


class Less_Formatter_Compressed extends Less_Formatter_Classic
{
	public $disableSingle = true;
	public $open = "{";
	public $selectorSeparator = ",";
	public $assignSeparator = ":";
	public $break = "";
	public $compressColors = true;

	public function indentStr($n = 0)
	{
		return "";
	}
}
