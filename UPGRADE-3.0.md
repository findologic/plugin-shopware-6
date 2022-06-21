# Upgrade from 2.x to 3.x

Changes: [2.8.2...3.0.0](https://github.com/findologic/plugin-shopware-6/compare/2.8.2...3.0.0)  
Changelog: [3.0.0 Release](https://github.com/findologic/plugin-shopware-6/releases/tag/3.0.0)

This file is **irrelevant** for you in case you do **not have a plugin extension**.

---

This file should help you upgrade from 2.x to 3.x, by providing you with
information that you are going to need, in case you have an extension plugin that
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

- `FINDOLOGIC\FinSearch\Controller\ExportController`
  - Added new member variables `$customerGroupRepository`, `$exportContext` and `$productSearcher`
  - Removed member variable `$pluginConfig`
  - Update the export logic to use our new export services
  - Implemented a logic, to still use the legacy export, when an outdated extension is installed

### Export

- `FINDOLOGIC\FinSearch\Export\Export`
  - The signature of method `buildItems()` has been updated to `Export::buildItems(array $productEntities): array`
- `FINDOLOGIC\FinSearch\Export\ProductIdExport`
  - The signature of method `buildItems()` has been updated to `ProductIdExport::buildItems(array $productEntities): array`
- `FINDOLOGIC\FinSearch\Export\ProductImageService`
  - The signature of method `getProductImages()` has been updated to 
    `ProductImageService::getProductImages(ProductEntity $product, bool $considerVariants = true): array`
- `FINDOLOGIC\FinSearch\Export\ProductService`
  - Method `addProductAssociations` now includes child associations separately
- `FINDOLOGIC\FinSearch\Export\XmlExport`
  - Added new member variables `$exportItemAdapter`, `$productSearcher` and `$eventDispatcher`
  - Changed `buildItems()` to `buildItemsLegacy()`
  - Changed `exportSingleItem()` to `exportSingleItemLegacy()`
  - The signature of method `buildItems()` has been updated to `XmlExport::buildItems(array $productEntities): array`
  - `buildItems()` now uses the new adapter logic and fetches the variants paginated

### Utils

- `FINDOLOGIC\FinSearch\Utils\Utils`
  - Child associations of `addProductAssociations()` were moved to `addChildrenAssociations()`
  - Split up `addProductAssociations()` in `addProductAssociations()` and `addVariantAssociations`

## New Services/Classes/Interfaces

### Export

- Introduced adapter services at `FINDOLOGIC\FinSearch\Export\Adapters`
  - Adapter classes were created for each type of data (name, summary, properties, attributes etc.)
  - Each of the classes is ignoring any kind of children data of the provided product
  - The adapter services are called for each variant separately
  - The whole export logic from `FINDOLOGIC\FinSearch\Struct\FindologicProduct` was split into the relevant adapters
- Introduced events at `FINDOLOGIC\FinSearch\Export\Events`
  - Manipulate the product data in one of the five new events. Before/after adapting a product/variant, or when the item
    was built successfully.
- `FINDOLOGIC\FinSearch\Export\ExportContext` includes the relevant information needed across the export
- `FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder`
  - Responsible to build the criteria for `FINDOLOGIC\FinSearch\Export\Search\ProductSearcher`
  - Replaces the criteria building from `FINDOLOGIC\FinSearch\Export\ProductService`
- `FINDOLOGIC\FinSearch\Export\Search\ProductSearcher`
  - Responsible for fetching the products from the database
  - Replaces the old `FINDOLOGIC\FinSearch\Export\ProductService`
