#!/bin/sh
rm -rf out
mkdir -p out
phar.phar pack -f out/plessc.phar -s plessc lessc.inc.php
chmod +x out/plessc.phar

phar.phar pack -f out/lessify.phar -s lessify lessify.inc.php lessc.inc.php
chmod +x out/lessify.phar
