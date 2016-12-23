COMPOSER_BIN = ./vendor/bin

test:
	$(COMPOSER_BIN)/phpunit ./tests

testweb:
	$(COMPOSER_BIN)/phpunit ./tests

phpcs:
	$(COMPOSER_BIN)/phpcs --standard=PSR2 app/
	$(COMPOSER_BIN)/phpcs --standard=PSR2 tests/

phpcbf:
	$(COMPOSER_BIN)/phpcbf --standard=PSR2 app/
	$(COMPOSER_BIN)/phpcbf --standard=PSR2 tests/

phpmd:
	$(COMPOSER_BIN)/phpmd ./app text cleancode,codesize,controversial,design,unusedcode,naming | grep -v 'Avoid using static access to class'

check: test phpcs phpmd

