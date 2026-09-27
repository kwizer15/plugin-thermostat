PHP_VERSION ?= 7.3
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
