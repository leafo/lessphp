#!/bin/sh

# creates tar.gz for current version

VERSION=`./plessc -v | sed -n 's/^v\(.*\)$/\1/p'`
OUT_DIR="tmp/lessphp"
TMP=`dirname $OUT_DIR`

mkdir -p $OUT_DIR
tar -c `git ls-files` | tar -C $OUT_DIR -x 

rm $OUT_DIR/.gitignore
rm $OUT_DIR/package.sh
rm $OUT_DIR/lessify
rm $OUT_DIR/lessify.inc.php

OUT_NAME="lessphp-$VERSION.tar.gz"
tar -czf $OUT_NAME -C $TMP lessphp/
echo "Wrote $OUT_NAME"

rm -r $TMP

