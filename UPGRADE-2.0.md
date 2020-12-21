# Upgrade from 1.x to 2.x

Changes: [v1.4.0...v2.0.0](https://github.com/findologic/plugin-shopware-6/compare/v1.4.0...2.0.0)  
Changelog: [v2.0.0 Release](https://github.com/findologic/plugin-shopware-6/releases/tag/v2.0.0)

This file is **irrelevant** for you in case you do **not have an extension plugin**.

---

This file should help you upgrade from 1.x to 2.x, by providing you with
information that you will need, in case you have an extension plugin that
overrides or implements any classes of the main plugin.  
Information about private methods won't be preserved.

## Deprecations

Methods, properties, method arguments, etc. that have previously been marked as `@deprecated`
have been removed, updated or replaced.

Extension plugins that **override the export, will break**. For a full overview
of all changes necessary for the extension plugin, please see changes below, or directly check the relevant changes:
[v1.0.1...v2.0.0](https://github.com/findologic/plugin-shopware-6-extension/compare/v1.0.1...v2.0.0).

### Export

* `FINDOLOGIC\FinSearch\Export\FindologicProductFactory`
  * Signature of method `FindologicProductFactory::buildInstance` has been updated to
    `buildInstance(ProductEntity $product, RouterInterface $router, ContainerInterface $container, string $shopkey, array $customerGroups, Item $item): FindologicProduct`.
* `FINDOLOGIC\FinSearch\Export\XmlProduct`
    * Signature of method `XmlProduct::__construct` has been updated to
      `__construct(ProductEntity $product, RouterInterface $router, ContainerInterface $container, string $shopkey, array $customerGroups)`.
### Struct

* `FINDOLOGIC\FinSearch\Struct\FindologicProduct`
    * Signature of method `FindologicProduct::__construct` has been updated to
      `__construct(ProductEntity $product, RouterInterface $router, ContainerInterface $container, string $shopkey, array $customerGroups, Item $item)`.

## Changes

### Controller

The `ExportController` received a refactoring, which enables extensions to easier override
things like product associations, or the output format. That being said, we want
plugin developers no longer to override the `ExportController` directly.  
**Use the [new exposed services]() instead**.

* Method `\FINDOLOGIC\FinSearch\Controller\ExportController::getProductCriteria(?int $offset = null, ?int $limit = null)`
 has been removed without replacement.
* Method `\FINDOLOGIC\FinSearch\Controller\ExportController::getTotalProductCount()`
 has been removed without replacement.
* Method `\FINDOLOGIC\FinSearch\Controller\ExportController::getTotalProductCount()`
has been removed without replacement.

## New Services/Classes/Interfaces

### Export Services

For easier extension, we have created several services to extend every bit of the export,
with as little effort as possible, while at the same time improving our code structure.  
We are planning to add more services for the export, when we add new features to it.

* `\FINDOLOGIC\FinSearch\Export\ProductService` is responsible for fetching the products from
 the database. Can be extended to:
  * Define exported products.
  * Define product associations.
