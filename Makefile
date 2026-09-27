PHP_VERSION ?= 7.0
image := plugin-thermostat-test:$(PHP_VERSION)
docker_run := docker run --rm -t -u $(shell id -u):$(shell id -g) \
	-v $(CURDIR):/jeedom/plugins/thermostat \
	-v $(CURDIR)/tests/fake-core:/jeedom/core:ro \
	-w /jeedom/plugins/thermostat \
	$(image)

vendor: composer.json
	composer install --no-interaction --no-progress
	touch $@

image:
	docker build -q -t $(image) --build-arg PHP_VERSION=$(PHP_VERSION) tests
.PHONY: image

tests: vendor image
	$(docker_run) php vendor/bin/phpunit $(ARGS)
.PHONY: tests

tools/phpstan/vendor: tools/phpstan/composer.json
	composer --working-dir=tools/phpstan install --no-interaction --no-progress
	touch $@

phpstan_run := docker run --rm -t -u $(shell id -u):$(shell id -g) -v $(CURDIR):/app -w /app php:8.3-cli \
	php -d memory_limit=-1 tools/phpstan/vendor/bin/phpstan analyse --configuration phpstan.neon --no-progress

phpstan: vendor tools/phpstan/vendor
	$(phpstan_run) $(ARGS)
.PHONY: phpstan

phpstan-baseline: vendor tools/phpstan/vendor
	$(phpstan_run) --generate-baseline phpstan-baseline.neon
.PHONY: phpstan-baseline
