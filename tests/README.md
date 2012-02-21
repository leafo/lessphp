## test.php

To run:

    php test.php [flags] [test-name-glob]


Runs through all files in `inputs`, compiles them, then compares to respective
file in `outputs`. If there are any differences then the test will fail.

Add the `-d` flag to show the differences of failed tests.  Defaults to showing
differences with `diff` but you can set the tool by doing `-d=toolname`.

Pass the `-C` flag to save the output of the inputs to the appropriate file. This
will overwrite any existing outputs. Use this when you want to save verified
test results. Combine with a *test-name-glob* to selectively compile.

You can also run specific tests by passing in an argument that contains any
part of the test name.

## bootstrap.sh

It's a syntetic test comparing lessc and lessphp output compiling twitter bootstrap;
see bootstrap.sh for details.