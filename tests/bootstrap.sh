#!/bin/sh

echo "This script clones twitter bootsrap, compiles it with lessc and lessphp,"
echo "cleans up results with sort.php, and outputs diff. To run it, you need to"
echo "have git and lessc installed."
echo ""

if [ -z "$@" ]; then
  diff_tool="diff -b -u -t -B"
else
  diff_tool=$@
fi

mkdir -p tmp

if [ ! -d 'bootstrap/' ]; then
  echo ">> Cloning bootstrap to bootstrap/"
  git clone https://github.com/twitter/bootstrap
fi

echo ">> Lessc compilation"
lessc bootstrap/less/bootstrap.less tmp/bootstrap.lessc.css

echo ">> Lessphp compilation"
../plessc bootstrap/less/bootstrap.less tmp/bootstrap.lessphp.css
echo ">> Cleanup and convert"

php sort.php tmp/bootstrap.lessc.css > tmp/bootstrap.lessc.clean.css
php sort.php tmp/bootstrap.lessphp.css > tmp/bootstrap.lessphp.clean.css

echo ">> Doing diff"
$diff_tool tmp/bootstrap.lessc.clean.css tmp/bootstrap.lessphp.clean.css
