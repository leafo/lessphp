lessphp uses [phpunit](https://github.com/sebastianbergmann/phpunit/) for its tests

* `InputTest.php` iterates through all the less files in `inputs/`, compiles
  them, then compares the result with the respective file in `outputs/`.

* `ApiTest.php` tests the behavior of lessphp's public API methods.

* `ErrorHandlingTest.php` tests that lessphp throws appropriate errors when
  given invalid LESS as input.

From the root you can run `make` to run all the tests.

## lessjs tests

Tests found in `inputs_lessjs` are extracted directly from
[less.js](https://github.com/less/less.js). The following license applies to
those tests: https://github.com/less/less.js/blob/master/LICENSE

## bootstrap.sh

Clones twitter bootsrap, compiles it with lessc and lessphp, cleans up results
with sort.php, and outputs diff. To run it, you need to have git and lessc
installed.

