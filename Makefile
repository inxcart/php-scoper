BOX=vendor-bin/box/vendor/bin/box
PHPUNIT=vendor/bin/phpunit
PHPSCOPER=bin/php-scoper.phar
BLACKFIRE=blackfire


.DEFAULT_GOAL := help
.PHONY: build test


help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Build
##---------------------------------------------------------------------------

clean:		## Clean all created artifacts
clean:
	git clean --exclude=.idea/ -fdx

build:		## Build the PHAR
build: bin/php-scoper src vendor vendor-bin/box/vendor scoper.inc.php box.json
	# Cleanup existing artefacts
	rm -f bin/php-scoper.phar

	# Remove unnecessary packages
	composer install --no-dev --prefer-dist

	# Prefixes the code to be bundled
	php -d zend.enable_gc=0 bin/php-scoper add-prefix --output-dir=build/php-scoper --force

	# Re-dump the loader to account for the prefixing
	# and optimize the loader
	composer dump-autoload --working-dir=build/php-scoper --classmap-authoritative --no-dev

	# Build the PHAR
	php -d zend.enable_gc=0 -d phar.readonly=0 $(BOX) build

	# Install back all the dependencies
	composer install


##
## Tests
##---------------------------------------------------------------------------

test:		## Run all the tests
test: tu e2e

tu:		## Run PHPUnit tests
tu: vendor/bin/phpunit
	php -d zend.enable_gc=0 $(PHPUNIT)

tc:		## Run PHPUnit tests with test coverage
tc: vendor/bin/phpunit
	phpdbg -qrr -d zend.enable_gc=0 $(PHPUNIT) --coverage-html=dist/coverage --coverage-text

e2e:		## Run end-to-end tests
e2e: e2e_004 e2e_005 e2e_011 e2e_013

e2e_004:	## Run end-to-end tests for the fixture set 004: source code case
e2e_004: bin/php-scoper.phar
	php -d zend.enable_gc=0 $(PHPSCOPER) add-prefix --working-dir=fixtures/set004 --output-dir=../../build/set004 --force --no-config --no-interaction
	composer --working-dir=build/set004 dump-autoload
	php -d zend.enable_gc=0 -d phar.readonly=0 $(BOX) build -c build/set004/box.json.dist

	php build/set004/bin/greet.phar > build/set004/output
	diff fixtures/set004/expected-output build/set004/output

e2e_005:	## Run end-to-end tests for the fixture set 005: third-party code case
e2e_005: bin/php-scoper.phar fixtures/set005/vendor
	php -d zend.enable_gc=0 $(PHPSCOPER) add-prefix --working-dir=fixtures/set005 --output-dir=../../build/set005 --force --no-config --no-interaction
	composer --working-dir=build/set005 dump-autoload
	php -d zend.enable_gc=0 -d phar.readonly=0 $(BOX) build -c build/set005/box.json.dist

	php build/set005/bin/greet.phar > build/set005/output
	diff fixtures/set005/expected-output build/set005/output

e2e_011:	## Run end-to-end tests for the fixture set 011: whitelist case
e2e_011: bin/php-scoper.phar fixtures/set011/vendor
	php -d zend.enable_gc=0 $(PHPSCOPER) add-prefix --working-dir=fixtures/set011 --output-dir=../../build/set011 --force --no-interaction
	cp -R fixtures/set011/tests build/set011/
	composer --working-dir=build/set011 dump-autoload
	php -d zend.enable_gc=0 -d phar.readonly=0 $(BOX) build -c build/set011/box.json.dist

	php build/set011/bin/greet.phar > build/set011/output
	diff fixtures/set011/expected-output build/set011/output

e2e_013:	# Run end-to-end tests for the fixture set 013: the init command
e2e_013: bin/php-scoper.phar
	rm -rf build/set013
	cp -R fixtures/set013 build/set013
	$(PHPSCOPER) init --working-dir=build/set013 --no-interaction
	diff src/scoper.inc.php.tpl build/set013/scoper.inc.php

tb:		## Run Blackfire profiling
tb: vendor
	rm -rf build
	rm -rf vendor-bin/*/vendor

	mv -f vendor tmp-back
	composer install --no-dev --prefer-dist --classmap-authoritative

	$(BLACKFIRE) --new-reference run php -d zend.enable_gc=0 bin/php-scoper add-prefix --output-dir=build/php-scoper --force --quiet

	rm -rf vendor
	mv -f tmp-back vendor


##
## Rules from files
##---------------------------------------------------------------------------

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install

vendor-bin/box/vendor: vendor-bin/box/composer.lock vendor/bamarni
	composer bin all install

fixtures/set005/vendor: fixtures/set005/composer.lock
	composer --working-dir=fixtures/set005 install

fixtures/set011/vendor: fixtures/set011/vendor
	composer --working-dir=fixtures/set011 dump-autoload

composer.lock: composer.json
	@echo composer.lock is not up to date.

vendor-bin/box/composer.lock: composer.lock
	composer install

fixtures/set005/composer.lock: fixtures/set005/composer.json
	@echo fixtures/set005/composer.lock is not up to date.

fixtures/set011/composer.lock: fixtures/set011/composer.json
	@echo fixtures/set011/composer.lock is not up to date.

bin/php-scoper.phar:
	$(MAKE) build

box.json:
	cat box.json.dist | sed -E 's/\"key\": \".+\",//g' | sed -E 's/\"algorithm\": \".+\",//g' > box.json
