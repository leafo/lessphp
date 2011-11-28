    title: v0.3.0 documentation
--

<h2 skip="true">Documentation v0.3.0</h2>

<div style="margin-bottom: 1em;">$index</div>

**lessphp** is a compiler that generates CSS from a superset language which
adds a collection of convenient features often seen in other languages. All CSS
is compatible with LESS, so you can start using new features with your existing CSS.

It is designed to be compatible with [less.js](http://lesscss.org), and suitable
as a drop in replacement for PHP projects.

## Getting Started

The homepage for **lessphp** can be found at [http://leafo.net/lessphp/][1].

You can follow development at the project's [GitHub][2].

Including **lessphp** in your project is as simple as dropping the single
include file into your code base and running the appropriate compile method as
described in the [PHP Interface](#php_interface).

  [1]: http://leafo.net/lessphp "lessphp homepage"
  [2]: https://github.com/leafo/lessphp "lessphp GitHub page"

## Installation

**lessphp** is distributed entirely in a single stand-alone file. Download the
latest version from either [the homepage][1] or [GitHub][2].

Development versions can also be downloading from GitHub.

Place `lessphp.inc.php` in a location available to your PHP scripts, and
include it. That's it! you're ready to begin.

## The Language

**lessphp** is very easy to learn because it generally functions how you would
expect it to. If you feel something is challenging or missing, feel free to
open an issue on the [bug tracker](https://github.com/leafo/lessphp/issues).

It is also easy to learn because any standards-compliant CSS code is valid LESS
code. You are free to gradually enhance your existing CSS code base with LESS
features without having to worry about rewriting anything.

The following is a description of the new languages features provided by LESS.

### Line Comments

Simple but very useful; line comments are started with `//`:

    ```less
    // this is a comment
    body {
      color: red; // as is this
      /* block comments still work also */
    }
    ```

### Variables
Variables are identified with a name that starts with `@`. To declare a
variable, you create an appropriately named CSS property and assign it a value:

    ```less
    @family: "verdana";
    @color: red;
    body {
      @mycolor: red;
      font-family: @family;
      color: @color;
      border-bottom: 1px solid @color;
    }
    ```

Variable declarations will not appear in the output. Variables can be declared
in the outer most scope of the file, or anywhere else a CSS property may
appear. They can hold any CSS property value.

Variables are only visible for use from their current scope, or any enclosed
scopes.

### Expressions

Expressions let you combine values and variables in meaningful ways. For
example you can add to a color to make it a different shade. Or divide up the
width of your layout logically. You can even concatenate strings.

Use the mathematical operators to evaluate an expression:

    ```less
    @width: 960px;
    .nav {
      width: @width / 3;
      color: #001 + #abc;
    }
    .body {
      width: 2 * @width / 3;
      font-family: "hel" + "vetica";
    }
    ```

Parentheses can be used to control the order of evaluation. They can also be
used to force an evaluation for cases where CSS's syntax makes the expression
ambiguous.

The following property will produce two numbers, instead of doing the
subtraction:

    ```less
    margin: 10px -5px;
    ```

To force the subtraction:

    ```less
    margin: (10px -5px);
    ```

It is also safe to surround mathematical operators by spaces to ensure that
they are evaluated:

    ```less
    margin: 10px - 5px;
    ```

Division has a special quirk. Due to CSS font shorthand syntax, we need to be
careful about how we place spaces. In the following example we are using font
size and lineheight shorthand. No division should take place:

    ```less
    .font {
      font: 20px/80px "Times New Roman";
    }
    ```

In order to force division we can surround the `/` by spaces, or we can wrap
the expression in parentheses:

    ```less
    .font {
      // these two will evaluate
      font: 20px / 80px "Times New Roman";
      font: (20px/80px) "Times New Roman";
    }
    ```

### Nested Blocks

By nesting blocks we can build up a chain of CSS selectors through scope
instead of repeating them. In addition to reducing repetition, this also helps
logically organize the structure of our CSS.

    ```less
    ol.list {
      li.special {
        border: 1px solid red; 
      }

      li.plain {
        font-weight: bold;
      }
    }
    ```


This will produce two blocks, a `ol.list li.special` and `ol.list li.plain`.

Blocks can be nested as deep as required in order to build a hierarchy of
relationships.

The `&` operator can be used in a selector to represent its parent's selector.
If the `&` operator is used, then the default action of appending the parent to
the front of the child selector separated by space is not performed.

    ```less
    b {
      a & {
        color: red;
      }

      // the following have the same effect

      & i {
        color: blue;
      }

      i {
        color: blue;
      }
    }
    ```


Because the `&` operator respects the whitespace around it, we can use it to
control how the child blocks are joined. Consider the differences between the
following:

    ```less
    div {
      .child-class { color: purple; }

      &.isa-class { color: green; }

      #child-id { height: 200px; }

      &#div-id { height: 400px; }

      &:hover { color: red; }

      :link { color: blue; }
    }
    ```

The `&` operator also works with [mixins](#mixins), which produces interesting results:

    ```less
    .within_box_style() {
      .box & {
        color: blue;
      }
    }

    #menu {
      .within_box_style;
    }
    ```

### Mixins

Any block can be mixed in just by naming it:

    ```less
    .mymixin {
      color: blue;
      border: 1px solid red;

      .special {
        font-weight: bold;
      }
    }


    h1 {
      font-size: 200px;
      .mixin;
    }
    ```

All properties and child blocks are mixed in.

Mixins can be made parametric, meaning they can take arguments, in order to
enhance their utility. A parametric mixin all by itself is not outputted when
compiled. Its properties will only appear when mixed into another block.

The canonical example is to create a rounded corners mixin that works across
browsers:
    
    ```less
    .rounded-corners(@radius: 5px) {
      border-radius: @radius;
      -webkit-border-radius: @radius;
      -moz-border-radius: @radius;
    }

    .header {
      .rounded-corners();
    }

    .info {
      background: red;
      .rounded-corners(14px);
    }
    ```

If you have a mixin that doesn't have any arguments, but you don't want it to
show up in the output, give it a blank argument list:

    ```less
    .secret() {
      font-size: 6000px;
    }
    
    .div {
      .secret;
    }
    ```

If the mixin doesn't need any arguments, you can leave off the parentheses when
mixing it in, as seen above.

You can also mixin a block that is nested inside other blocks. You can think of
the outer block as a way of making a scope for your mixins. You just list the
names of the mixins separated by spaces, which describes the path to the mixin
you want to include. Optionally you can separate them by `>`.

    ```less
    .my_scope  {
      .some_color {
        color: red;
        .inner_block {
          text-decoration: underline;
        }
      }
      .bold {
        font-weight: bold;
        color: blue;
      }
    }

    .a_block {
      .my_scope .some_color;
      .my_scope .some_color .inner_block;
    }

    .another_block {
      // the alternative syntax
      .my_scope > .bold;
    }
    ```

### Import

Multiple LESS files can be compiled into a single CSS file by using the
`@import` statement. Be careful, the LESS import statement shares syntax with
the CSS import statement. If the file being imported ends in a `.less`
extension, or no extension, then it is treated as a LESS import. Otherwise it
is left alone and outputted directly:

    ```less
    // my_file.less
    .some-mixin(@height) {
      height: @height;
    }

    // main.less
    @import "main.less" // will import the file if it can be found
    @import "main.css" // will be left alone

    body {
      .some-mixin(400px);
    }
    ```

All of the following lines are valid ways to import the same file:

    ```less
    @import "file";
    @import 'file.less';
    @import url("file");
    @import url('file');
    @import url(file);
    ```

When importing, the `importDir` is searched for files. This can be configured,
see [PHP Interface](#php_interface).

### String Interpolation

String interpolation is a convenient way to insert the value of a variable
right into a string literal. Given some variable named `@var_name`, you just
need to write it as `@{var_name}` from within the string to have its value
inserted:

    ```less
    @symbol: ">";
    h1:before {
      content: "@{symbol}: ";
    }

    h2:before {
      content: "@{symbol}@{symbol}: ";
    }
    ```

There are two kinds of strings, implicit and explicit strings. Explicit strings
are wrapped by double quotes, `"hello I am a string"`, or single quotes `'I am
another string'`. Implicit strings only appear when using `url()`. The text
between the parentheses is considered a string and thus string interpolation is
possible:

    ```less
    @path: "files/";
    body {
      background: url(@{path}my_background.png);
    }
    ```

### String Format Function

The `%` function can be used to insert values into strings using a *format
string*. It works similar to `printf` seen in other languages. It has the
same purpose as string interpolation above, but gives explicit control over
the output format.

    ```less
    @symbol: ">";
    h1:before {
      content: %("%s: ", @symbol);
    }
    ```

The `%` function takes as its first argument the format string, following any
number of addition arguments that are inserted in place of the format
directives.

A format directive starts with a `%` and is followed by a single character that
is either `a`, `d`, or `s`:

    ```less
    strings: %("%a %d %s %a", hi, 1, 'ok', 'cool');
    ```

`%a` and `%d` format the value the same way: they compile the argument to its
CSS value and insert it directly. When used with a string, the quotes are
included in the output. This typically isn't what we want, so we have the `%s`
format directive which strips quotes from strings before inserting them.

The `%d` directive functions the same as `%a`, but is typically used for numbers
assuming the output format of numbers might change in the future.

### String Unquoting

Sometimes you will need to write proprietary CSS syntax that is unable to be
parsed. As a workaround you can place the code into a string and unquote it.
Unquoting is the process of outputting a string without its surrounding quotes.
There are two ways to unquote a string.

The `~` operator in front of a string will unquote that string:

    ```less
    .class {
      // a made up, but problematic vendor specific CSS
      filter: ~"Microsoft.AlphaImage(src='image.png')";
    }
    ```

If you are working with other types, such as variables, there is a built in
function that let's you unquote any value. It is called `e`.

    ```less
    @color: "red";
    .class {
      color: e(@color);
    }
    ```

### Built In Functions

**lessphp** has a collection of built in functions:

* `e(str)` -- returns a string without the surrounding quotes.
  See [String Unquoting](#string_unquoting)

* `floor(number)` -- returns the floor of a numerical input
* `round(number)` -- returns the rounded value of numerical input

* `lighten(color, percent)` -- lightens color by percent and returns it
* `darken(color, percent)` -- darkens color by percent and returns it

* `saturate(color, percent)` -- saturates color by percent and returns it
* `desaturate(color, percent)` -- desaturates color by percent and returns it

* `fadein(color, percent)` -- makes color less transparent by percent and returns it
* `fadeout(color, percent)` -- makes color more transparent by percent and returns it

* `spin(color, amount)` -- returns a color with amount degrees added to hue

* `rgbahex(color)` -- returns a string containing 4 part hex color.
   
   This is used to convert a CSS color into the hex format that IE's filter
   method expects when working with an alpha component.
   
       ```less
       .class {
          @start: rgbahex(rgba(25, 34, 23, .5));
          @end: rgbahex(rgba(85, 74, 103, .6));
          // abridged example
          -ms-filter:
            e("gradient(start=@{start},end=@{end})");
       }
       ```

## PHP Interface

The PHP interface lets you control the compiler from your PHP scripts. There is
only one file to include to get access to everything:

    ```php
    <?php
    include "lessc.inc.php";
    ```

To compile a file to a string (of CSS code):

    ```php
    $less = new lessc("myfile.less");
    $css = $less->parse();
    ```

To compile a string to a string:

    ```php
    $less = new lessc(); // a blank lessc
    $css = $less->parse("body { a { color: red } }");
    ```

Often, you want to write the compiled CSS to a file, and only recompile when
the original LESS file has changed. The following function will check the
modification date of the LESS file to see if a compile is required:

    ```php
    lessc::ccompile('myfile.less', 'mystyle.css');
    ```

All of the following methods will throw an `Exception` if the parsing fails:

    ```php
    $less = new lessc();
    try {
        $less->parse("} invalid LESS }}}");
    } catch (Exception $ex) {
        echo "lessphp fatal error: ".$ex->getMessage();
    }
    ```

### Setting Variables From PHP 

The `parse` function takes a second optional argument. If you want to
initialize variables from outside the LESS file then you can pass in an
associative array of names and values. The values will parsed as CSS values:

    ```php
    $less = new lessc();
    echo $less->parse(".magic { color: @color;  width: @base - 200; }", 
        array(
            'color' => 'red';
            'base' => '960px';
        ));
    ```

You can also do this when loading from a file, but remember to set the first
argument of the parse function to `null`, otherwise it will try to compile that
instead of the file:

    ```php
    $less = new lessc("myfile.less");
    echo $less->parse(null, array('color' => 'blue'));
    ```

### Custom Functions

**lessphp** has a simple extension interface where you can implement user
functions that will be exposed in LESS code during the compile. They can be a
little tricky though because you need to work with the  **lessphp** type system.

By sub-classing `lessc`, and creating specially named methods we can extend
**lessphp**. In order for a function to be visible in LESS, its name must
start with `lib_`.

Let's make a function that doubles any numeric argument.

    ```php
    <?php
    include "lessc.inc.php";

    class myless extends lessc {
        function lib_double($arg) {
            list($type, $value) = $arg;
            return array($type, $value*2);
        }
    }

    $myless = new myless();
    echo $myless->parse("div { width: double(400px); }");
    ```

Although a little verbose, the implementation of `lib_double` gives us some
insight on the type system. All values are stored in an array where the 0th
element is a string representing the type, and the other elements make up the
associated data for that value.

The best way to get an understanding of the system is to make a dummy `lib_`
function which does a `vardump` on the argument. Try passing the function
different values from LESS and see what the results are.

The return value of the `lib_` function must also be a LESS type, but if it is
a string or numeric value, it will automatically be coerced into an appropriate
typed value. In our example, we reconstruct the value with our modifications
while making sure that we preserve the type.

All of the built in functions are implemented in this manner within the `lessc`
class.

## Command Line Interface

**lessphp** comes with a command line script written in PHP that can be used to
invoke the compiler from the terminal. On Linux an OSX, all you need to do is
place `plessc` and `lessc.inc.php` somewhere in your PATH (or you can run it in
the current directory as well). On windows you'll need a copy of `php.exe` to
run the file. To compile a file, `input.less` to CSS, run:

    ```bash
    $ plessc input.less
    ```

To write to a file, redirect standard out:

    ```bash
    $ plessc input.less > output.css
    ```

To compile code directly on the command line:

    ```bash
    $ plessc -r "@color: red; body { color: @color; }"
    ```

To watch a file for changes, and compile it as needed, use the `-w` flag:

    ```bash
    $ plessc -w input-file output-file
    ```

Errors from watch mode are written to standard out.


## License

Copyright (c) 2010 Leaf Corcoran, <http://leafo.net/lessphp>
 
Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:
 
The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.
 
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.


*Also under GPL3 if required, see `LICENSE` file*

