# FireLess

FireLess is a Firebug extension
that makes Firebug display the Less filenames and line numbers
of LessPHP-generated CSS styles
rather than those of the generated CSS.
This is an adaptation of the [Firesass extension](https://github.com/nex3/firesass) developped by [Nex3](https://github.com/nex3/firesass)

![Screenshot](http://github.com/clearideaz/lessphp/raw/master/extra/screenshot.png)

## Usage

First, [install FireLess](https://github.com/clearideaz/lessphp/extra/fireless_for_firebug-0.0.5-fx.xpi).
Second, enable LessPHP's `debug_info` option.

## Compatibility

FireLess requires [LessPHP](http://leafo.net/lessphp/).

FireLess should work with all versions of Firefox after and including 3.0,
and all FireBug versions 1.4 and 1.5.
It might work with FireBug 1.6 (which is in development at time of writing),
but that's not guaranteed.

FireLess currently requires the development version of Less,
available from [GitHub](https://github.com/leafo/lessphp/).
