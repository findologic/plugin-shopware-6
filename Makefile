.DEFAULT_GOAL := help

## Variable definition
BASE_URL?=http://docker.vm:8000
PLUGIN_ROOT=$(shell cd -P -- '$(shell dirname -- "$0")' && pwd -P)
PROJECT_ROOT=$(PLUGIN_ROOT)/../../../

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
.PHONY: help

administration-fix: ## Run eslint on the administration files
	../../../vendor/shopware/platform/src/Administration/Resources/app/administration/node_modules/.bin/eslint --config .eslintrc-administration.js --ext .js,.vue src/Resources/app/administration --resolve-plugins-relative-to ../../../vendor/shopware/platform/src/Administration/Resources/app/administration --fix
.PHONY: administration-fix

storefront-fix: ## Run eslint on the storefront files
	../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/node_modules/.bin/eslint --config .eslintrc-storefront.js --ext .js,.vue src/Resources/app/storefront --resolve-plugins-relative-to ../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront --fix
.PHONY: storefront-fix

administration-lint: ## Run eslint on the administration files
	../../../vendor/shopware/platform/src/Administration/Resources/app/administration/node_modules/.bin/eslint --config .eslintrc-administration.js --ext .js,.vue src/Resources/app/administration --resolve-plugins-relative-to ../../../vendor/shopware/platform/src/Administration/Resources/app/administration
.PHONY: administration-lint

storefront-lint: ## Run eslint on the storefront files
	../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/node_modules/.bin/eslint --config .eslintrc-storefront.js --ext .js,.vue src/Resources/app/storefront --resolve-plugins-relative-to ../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront
.PHONY: storefront-lint
