    title: v0.3.4 documentation
    link_to_home: true
--

<h2 skip="true">Documentation v0.3.4</h2>

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

If you have a string or keyword in a variable, you can reference another
variable by that name by repeating the `@`:

    ```less
    @value: 20px;
    @value_name: "value";

    width: @@value_name;
    ```

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

Division has a special quirk. There are certain CSS properties that use the `/`
operator as part of their value's syntax. Namely, the [font][4] shorthand and
[border-radius][3].

  [3]: https://developer.mozilla.org/en/CSS/border-radius
  [4]: https://developer.mozilla.org/en/CSS/font


Thus, **lessphp** will ignore any division in these properties unless it is
wrapped in parentheses. For example, no division will take place here:

    ```less
    .font {
      font: 20px/80px "Times New Roman";
    }
    ```

In order to force division we must wrap the expression in parentheses:

    ```less
    .font {
      font: (20px/80px) "Times New Roman";
    }
    ```

If you want to write a literal `/` expression without dividing in another
property (or a variable), you can use [string unquoting](#string_unquoting):

    ```less
    .var {
      @size: ~"20px/80px";
      font: @size sans-serif;
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

#### `@arguments` Variable

Within an mixin there is a special variable named `@arguments` that contains
all the arguments passed to the mixin along with any remaining arguments that
have default values. The value of the variable has all the values separated by
spaces.

This useful for quickly assigning all the arguments:

    ```less
    .box-shadow(@x, @y, @blur, @color) {
      box-shadow: @arguments;
      -webkit-box-shadow: @arguments;
      -moz-box-shadow: @arguments;
    }
    .menu {
      .box-shadow(1px, 1px, 5px, #aaa);
    }
    ```

In addition to the arguments passed to the mixin, `@arguments` will also include
remaining default values assigned by the mixin:


    ```less
    .border-mixin(@width, @style: solid, @color: black) {
      border: @arguments;
    }

    pre {
      .border-mixin(4px, dotted);
    }

    ```


#### Pattern Matching

When you *mix in* a mixin, all the available mixins of that name in the current
scope are checked to see if they match based on what was passed to the mixin
and how it was declared.

The simplest case is matching by number of arguments. Only the mixins that
match the number of arguments passed in are used.

    ```less
    .simple() { // matches no arguments
      height: 10px;
    }

    .simple(@a, @b) { // matches two arguments
      color: red;
    }

    .simple(@a) { // matches one argument
      color: blue;
    }

    div {
      .simple(10);
    }

    span {
      .simple(10, 20);
    }
    ```

Whether an argument has default values is also taken into account when matching
based on number of arguments:

    ```less
    // matches one or two arguments
    .hello(@a, @b: blue) {
      height: @a;
      color: @b;
    }

    .hello(@a, @b) { // matches only two
      width: @a;
      border-color: @b;
    }

    .hello(@a) { // matches only one
      padding: 1em;
    }

    div {
      .hello(10px);
    }

    pre {
      .hello(10px, yellow);
    }
    ```

Additionally, a *vararg* value can be used to further control how things are
matched.  A mixin's argument list can optionally end in the special argument
named `...`.  The `...` may match any number of arguments, including 0.

    ```less
    // this will match any number of arguments
    .first(...) {
      color: blue;
    }

    // matches at least 1 argument
    .second(@arg, ...) {
      height: 200px + @arg;
    }

    div { .first("some", "args"); }
    pre { .second(10px); }
    ```

If you want to capture the values that get captured by the *vararg* you can
give it a variable name by putting it directly before the `...`. This variable
must be the last argument defined. It's value is just like the special
[`@arguments` variable](#arguments_variable), a space separated list.


    ```less
    .hello(@first, @rest...) {
      color: @first;
      text-shadow: @rest;
    }

    span {
      .hello(red, 1px, 1px, 0px, white);
    }

    ```

Another way of controlling whether a mixin matches is by specifying a value in
place of an argument name when declaring the mixin:

    ```less
    .style(old, @size) {
      font: @size serif;
    }

    .style(new, @size) {
      font: @size sans-serif;
    }

    .style(@_, @size) {
      letter-spacing: floor(@size / 6px);
    }

    em {
      @switch: old;
      .style(@switch, 15px);
    }
    ```

Notice that two of the three mixins were matched. The mixin with a matching
first argument, and the generic mixin that matches two arguments. It's common
to use `@_` as the name of a variable we intend to not use. It has no special
meaning to LESS, just to the reader of the code.

#### Guards

Another way of restricting when a mixin is mixed in is by using guards. A guard
is a special expression that is associated with a mixin declaration that is
evaluated during the mixin process. It must evaluate to true before the mixin
can be used.

We use the `when` keyword to begin describing a list of guard expressions.

Here's a simple example:

    ```less
    .guarded(@arg) when (@arg = hello) {
      color: blue;
    }

    div {
      .guarded(hello); // match
    }

    span {
      .guarded(world); // no match
    }
    ```
Only the `div`'s mixin will match in this case, because the guard expression
requires that `@arg` is equal to `hello`.

We can include many different guard expressions by separating them by commas.
Only one of them needs to match to trigger the mixin:

    ```less
    .x(@a, @b) when (@a = hello), (@b = world) {
      width: 960px;
    }

    div {
      .x(hello, bar); // match
    }

    span {
      .x(foo, world); // match
    }

    pre {
      .x(foo, bar); // no match
    }
    ```

Instead of a comma, we can use `and` keyword to make it so all of the guards
must match in order to trigger the mixin. `and` has higher precedence than the
comma.

    ```less
    .y(@a, @b) when (@a = hello) and (@b = world) {
      height: 600px;
    }

    div {
      .y(hello, world); // match
    }

    span {
      .y(hello, bar); // no match
    }
    ```

Commas and `and`s can be mixed and matched.

You can also negate a guard expression by using `not` in from of the parentheses:

    ```less
    .x(@a) when not (@a = hello) {
      color: blue;
    }

    div {
      .x(hello); // no match
    }
    ```

The `=` operator is used to check equality between any two values. For numbers
the following comparison operators are also defined:

`<`, `>`, `=<`, `>=`

There is also a collection of predicate functions that can be used to test the
type of a value.

These are `isnumber`, `iscolor`, `iskeyword`, `isstring`, `ispixel`,
`ispercentage` and `isem`.

    ```less
    .mix(@a) when (ispercentage(@a)) {
      height: 500px * @a;
    }
    .mix(@a) when (ispixel(@a)) {
      height: @a;
    }

    div.a {
      .mix(50%);
    }

    div.a {
      .mix(350px);
    }
    ```

### Selector Expressions

Sometimes we want to dynamically generate the selector of a block based on some
variable or expression. We can do this by using *selector expressions*. Selector
expressions are CSS selectors that are evaluated in the current scope before
being written out.

A simple example is a mixin that dynamically creates a selector named after the
mixin's argument:

    ```less
    .create-selector(@name) {
      (e(@name)) {
        color: red;
      }
    }

    .create-selector("hello");
    .create-selector("world");
    ```

Any selector that is enclosed in `()` will have it's contents evaluated and
directly written to output. The value is not changed any way before being
outputted, thats why we use the `e` function. If you're not familiar, the `e`
function strips quotes off a string value. If we didn't have it, then the
selector would have quotes around it, and that's not valid CSS!

Any value can be used in a selector expression, but it works best when using
strings and things like [String Interpolation](#string_interpolation).

Here's an interesting example adapted from Twitter Bootstrap. A couple advanced
things are going on. We are using [Guards](#guards) along with a recursive
mixin to work like a loop to generate a series of CSS blocks.


    ```less
    // create our recursive mixin:
    .spanX (@index) when (@index > 0) {
      (~".span@{index}") {
        width: @index * 100px;
      }
      .spanX(@index - 1);
    }
    .spanX (0) {}

    // mix it into the global scopee:
    .spanX(4);
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

* `lighten(color, percent)` -- lightens `color` by `percent` and returns it
* `darken(color, percent)` -- darkens `color` by `percent` and returns it

* `saturate(color, percent)` -- saturates `color` by `percent` and returns it
* `desaturate(color, percent)` -- desaturates `color` by `percent` and returns it

* `fadein(color, percent)` -- makes `color` less transparent by `percent` and returns it
* `fadeout(color, percent)` -- makes `color` more transparent by `percent` and returns it

* `spin(color, amount)` -- returns a color with `amount` degrees added to hue

* `fade(color, amount)` -- returns a color with the alpha set to `amount`

* `hue(color)` -- returns the hue of `color`

* `saturation(color)` -- returns the saturation of `color`

* `lightness(color)` -- returns the lightness of `color`

* `alpha(color)` -- returns the alpha value of `color` or 1.0 if it doesn't have an alpha

* `percentage(number)` -- converts a floating point number to a percentage, e.g. `0.65` -> `65%`

* `mix(color1, color1, percent)` -- mixes two colors by percentage where 100%
  keeps all of `color1`, and 0% keeps all of `color2`. Will take into account
  the alpha of the colors if it exists. See
  <http://sass-lang.com/docs/yardoc/Sass/Script/Functions.html#mix-instance_method>.

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

### Output Formatting

Besides the default output formatter, **lessphp** comes with two additional
ones, and it's easy to make your own.

The first extra formatter is called `compressed`. It compresses the output by
removing any extra whitespace.

We use the `setFormatter` method set the formatter that should be used. Just
pass the name of the formatter:

    ```php
    $less = new lessc("myfile.less");

    $less->setFormatter("compressed");

    $css = $less->parse();
    ```

The second formatter is called `indent`. It will indent CSS blocks based on how
they were nested in the LESS code.

#### Custom Formatter

The easiest way to customize is to create your own instance of the formatter
and alter its public properties before passing it off to **lessphp**. The
`setFormatter` method can also take an instance of a formatter.

For example, let's use tabs instead of the default two spaces to indent:

    ```php
    $formatter = new lessc_formatter;
    $formatter->indentChar = "\t";

    $less = new lessc("myfile.less");
    $less->setFormatter($formatter);
    $css = $less->parse();
    ```

For more information about what can be configured with the formatter consult
the sourcecode.

### Compiling Automatically

Often, you want to write the compiled CSS to a file, and only recompile when
the original LESS file has changed. The following function will check if the
modification date of the LESS file is more recent than the CSS file.  The LESS
file will be compiled if it is. If the CSS file doesn't exist yet, then it will
also compile the LESS file.

    ```php
    lessc::ccompile('myfile.less', 'mystyle.css');
    ```

`ccompile` is very basic, it only checks if the input file's modification time.
It is not of any files that are brought in using `@import`.

For this reason we also have `lessc::cexecute`. It functions slightly
differently, but gives us the ability to check changes to all files used during
the compile. It takes one argument, either the name of the file we want to
compile, or an existing *cache object*. Its return value is an updated cache
object.

If we don't have a cache object, then we call the function with the name of the
file to get the initial cache object. If we do have a cache object, then we
call the function with it. In both cases, an updated cache object is returned.

The cache object keeps track of all the files that must be checked in order to
determine if a rebuild is required.

The cache object is a plain PHP `array`. It stores the last time it compiled in
`$cache['updated']` and output of the compile in `$cache['compiled']`.

Here we demonstrate creating an new cache object, then using it to see if we
have a recompiled version available to be written:


    ```php
    $less_file = 'myfile.less';
    $css_file = 'myfile.css';

    // create a new cache object, and compile
    $cache = lessc::cexecute('myfile.less');
    file_put_contents($css_file, $cache['compiled']);

    // the next time we run, write only if it has updated
    $last_updated = $cache['updated'];
    $cache = lessc::cexecute($cache);
    if ($cache['updated'] > $last_updated) {
        file_put_contents($css_file, $cache['compiled']);
    }

    ```

In order for the system to fully work, we must save cache object between
requests. Because it's a plain PHP `array`, it's sufficient to
[`serialize`](http://php.net/serialize) it and save it the string somewhere
like a file or in persistent memory.

An example with saving cache object to a file:

    ```php
    function auto_compile_less($less_fname, $css_fname) {
      // load the cache
      $cache_fname = $less_fname.".cache";
      if (file_exists($cache_fname)) {
        $cache = unserialize(file_get_contents($cache_fname));
      } else {
        $cache = $less_fname;
      }

      $new_cache = lessc::cexecute($cache);
      if (!is_array($cache) || $new_cache['updated'] > $cache['updated']) {
        file_put_contents($cache_fname, serialize($new_cache));
        file_put_contents($css_fname, $new_cache['compiled']);
      }
    }

    auto_compile_less('myfile.less', 'myfile.css');
    ```

`lessc:cexecute` takes an optional second argument, `$force`. Passing in true
will cause the input to always be recompiled.

### Error Handling

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

You can also do this when loading from a file. If the first argument of `parse`
is an array it will be used an array of variables to set.

    ```php
    $less = new lessc("myfile.less");
    echo $less->parse(array('color' => 'blue'));
    ```

### Custom Functions

**lessphp** has a simple extension interface where you can implement user
functions that will be exposed in LESS code during the compile. They can be a
little tricky though because you need to work with the  **lessphp** type system.

An instance of `lessc`, the **lessphp** compiler has two relevant methods:
`registerFunction` and `unregisterFunction`. `registerFunction` takes two
arguments, a name and a callable value. `unregisterFunction` just takes the
name of an existing function to remove.

Here's an example that adds a function called `double` that doubles any numeric
argument:

    ```php
    <?php
    include "lessc.inc.php";

    function lessphp_double($arg) {
        list($type, $value) = $arg;
        return array($type, $value*2);
    }

    $myless = new myless();
    $myless->registerFunction("double", "lessphp_double");

    // gives us a width of 800px
    echo $myless->parse("div { width: double(400px); }");
    ```

The second argument to `registerFunction` is any *callable value* that is
understood by [`call_user_func`](http://php.net/call_user_func).

If we are using PHP 5.3 or above then we are free to pass a function literal
like so:

    ```php
    $myless->registerFunction("double", function($arg) {
        list($type, $value) = $arg;
        return array($type, $value*2);
    });
    ```

Now let's talk about the `double` function itself.

Although a little verbose, the implementation gives us some insight on the type
system. All values in **lessphp** are stored in an array where the 0th element
is a string representing the type, and the other elements make up the
associated data for that value.

The best way to get an understanding of the system is to register is dummy
function which does a `vardump` on the argument. Try passing the function
different values from LESS and see what the results are.

The return value of the registered function must also be a **lessphp** type, but if it is
a string or numeric value, it will automatically be coerced into an appropriate
typed value. In our example, we reconstruct the value with our modifications
while making sure that we preserve the original type.

In addition to the arguments passed from **lessphp**, the instance of
**lessphp** itself is sent to the registered function as the second argument.

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

