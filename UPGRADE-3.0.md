# Upgrade from 2.x to 3.x

Changes: [2.8.2...3.0.0](https://github.com/findologic/plugin-shopware-6/compare/2.8.2...3.0.0)  
Changelog: [3.0.0 Release](https://github.com/findologic/plugin-shopware-6/releases/tag/3.0.0)

This file is **irrelevant** for you in case you do **not have an extension plugin**.

---

This file should help you upgrade from 2.x to 3.x, by providing you with
information that you will need, in case you have an extension plugin that
overrides or implements any classes of the main plugin.  
Information about private methods won't be preserved.

## Deprecations

Methods, properties, method arguments, etc. that have previously been marked as `@deprecated`
have been removed, updated or replaced.

### New deprecations

The classes relevant to the old export logic are now deprecated and will be removed in v4.0

#### Export

- `FINDOLOGIC\FinSearch\Export\FindologicProductFactory`
  - Class and service declaration will be removed
- `FINDOLOGIC\FinSearch\Export\ProductService`
  - Class and service declaration will be removed

#### Struct

- `FINDOLOGIC\FinSearch\Struct\FindologicProduct`
  - Class will be removed

## Changes

### Controller

- Added new services as member variables
- Update the export logic to use our new export services
- Implemented a logic, to still use the legacy export, when an outdated extension is installed

### Export

- Introduced adapter services at `FINDOLOGIC\FinSearch\Export\Adapters`
  - Adapter classes were created for each type of data (name, summary, properties, attributes etc.)
  - Each of the classes is ignoring any kind of children data of the provided product
  - The adapter services are called for each variant separately
  - The whole export logic from `FINDOLOGIC\FinSearch\Struct\FindologicProduct` was split into the relevant adapters
- Introduced events at `FINDOLOGIC\FinSearch\Export\Events`
  - Manipulate the product data in one of the four new events. Before/after adapting a product/variant.
