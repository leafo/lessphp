# lessphp v0.3.4-2
### <http://leafo.net/lessphp>

`lessphp` is a compiler for LESS written in PHP. The documentation is great,
so check it out: <http://leafo.net/lessphp/docs/>.

Here's a quick tutorial:

### How to use in your PHP project

Copy `lessc.inc.php` to your include directory and include it into your project.

There are a few ways to interface with the compiler. The easiest is to have it
compile a LESS file when the page is requested. The static function 
`lessc::ccompile`, checked compile, will compile the input LESS file only when it
is newer than the output file.

	try {
		lessc::ccompile('input.less', 'output.css');
	} catch (exception $ex) {
		exit($ex->getMessage());
	}

`lessc::ccompile` is not aware of imported files that change. Read [about
`lessc::cexecute`](http://leafo.net/lessphp/docs/#compiling_automatically).

Note that all failures with lessc are reported through exceptions.
If you need more control you can make your own instance of lessc.

	$input = 'mystyle.less';

	$lc = new lessc($input);

	try {
		file_put_contents('mystyle.css', $lc->parse());
	} catch (exception $ex) { ... }

In addition to loading from file, you can also parse from a string like so:

	$lc = new lessc();
	$lesscode = 'body { ... }';
	$out = $lc->parse($lesscode);

### How to use from the command line

An additional script has been included to use the compiler from the command
line. In the simplest invocation, you specify an input file and the compiled
css is written to standard out:

	$ plessc input.less > output.css

Using the -r flag, you can specify LESS code directly as an argument or, if 
the argument is left off, from standard in:

	$ plessc -r "my less code here"

Finally, by using the -w flag you can watch a specified input file and have it 
compile as needed to the output file

	$ plessc -w input-file output-file

Errors from watch mode are written to standard out.

`lessphp` also supports output formatters. To compress the output run this:

	$ plessc -f=compressed myfile.less

For more help, run `plessc --help`

