# Upgrade from 3.x to 4.x

Changes: [3.1.3...4.0.0](https://github.com/findologic/plugin-shopware-6/compare/3.1.3...4.0.0)  
Changelog: [4.0.0 Release](https://github.com/findologic/plugin-shopware-6/releases/tag/4.0.0)

This file is **irrelevant** for you in case you do **not have a plugin extension**.

---

This file should help you upgrade from 3.x to 4.x, by providing you with
information that you are going to need, in case you have an extension plugin that
overrides or implements any classes of the main plugin.  
Information about private methods won't be preserved.

## Deprecations

Methods, properties, method arguments, etc. that have previously been marked as `@deprecated`
have been removed, updated or replaced.

## Changes

### General

- Upgraded code style to PHP 7.4
  - Added typehints for member variables
- Introduced library [shopware6-common](https://github.com/findologic/shopware6-common)

### Controller

- `FINDOLOGIC\FinSearch\Controller\ExportController`
  - Changed whole constructor signature
  - Refactored function structure
- `FINDOLOGIC\FinSearch\Controller\ProductDebugController`
  - Changed function structures to accommodate to `ExportController` changes

### Core

- `FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`
  - Removed function `handleResult()`
- `FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search\ProductSearchRoute`
  - Removed member variable and constructor argument `$shopwareVersion`
- `FINDOLOGIC\FinSearch\Core\Content\Product\SearchKeyword\ProductSearchBuilder`
  - Removed member variable and constructor argument `$shopwareVersion`
  - Removed legacy build function and renamed function to `doBuild()`

### Exceptions

- Moved some exceptions to shopware6-common

### Export

- `DynamicProductGroupService`
  - Moved to `FINDOLOGIC\FinSearch\Export\Services`
  - Extends `FINDOLOGIC\Shopware6Common\Export\Services\AbstractDynamicProductGroupService`
  - Reworked whole functionality
- `Export`, `XmlExport` and `ProductIdExport`
  - Moved to `FINDOLOGIC\FinSearch\Export\Types`
- `ExportContext`
  - Moved to `FINDOLOGIC\Shopware6Common\Export`
  - Added more member variables and constructor arguments
- `HeaderHandler`
  - Moved to `FINDOLOGIC\FinSearch\Export\Handlers`
  - Extends `FINDOLOGIC\Shopware6Common\Export\Handlers\AbstractHeaderHandler`
- `ProductImageService`
  - Moved to `FINDOLOGIC\Shopware6Common\Export\Services`
- `SalesChannelService`
  - Moved to `FINDOLOGIC\FinSearch\Export\Services`
- `UrlBuilderService`
  - Moved to `FINDOLOGIC\Shopware6Common\Export\Services`
  - Split up into `FINDOLOGIC\Shopware6Common\Export\Services\ProductUrlService` and `FINDOLOGIC\FinSearch\Export\Services\CatUrlBuilderService`

### Export/Adapters

- Moved all adapter classes except `PriceAdapter` and `SalesFrequencyAdapter`
- Argument type for `adapt()` changed to `Vin\ShopwareSdk\Data\Entity\Product\ProductEntity`

- `FINDOLOGIC\FinSearch\Export\Adapters\PriceAdapter`
  - Extends `FINDOLOGIC\Shopware6Common\Export\Adapters\PriceAdapter`
  - Changed constructor signature
  - Renamed function to `getPriceFromProduct`
  - Added check for Advanced Pricing to `adapt()`
- `FINDOLOGIC\FinSearch\Export\Adapters\SalesFrequencyAdapter`
  - Extends `FINDOLOGIC\Shopware6Common\Export\Adapters\AbstractSalesFrequencyAdapter`

### Export/Debug

- `FINDOLOGIC\FinSearch\Export\Debug\ProductDebugSearcher`
  - Moved to `FINDOLOGIC\FinSearch\Export\Search\ProductDebugSearcher`
  - Implements `FINDOLOGIC\Shopware6Common\Export\Search\ProductDebugSearcherInterface`

### Export/Errors

- Moved classes to shopware6-common

### Export/Events

- Moved classes to shopware6-common

### Export/Logger

- Moved classes to shopware6-common

### Export/Search

- `FINDOLOGIC\FinSearch\Export\Search\ProductCriteriaBuilder`
  - Extends `FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductCriteriaBuilder`
  - Changed constructor signature
  - Changed some function signatures
- `FINDOLOGIC\FinSearch\Export\Search\ProductSearcher`
  - Extends `FINDOLOGIC\Shopware6Common\Export\Search\AbstractProductSearcher`
  - Changed constructor signature
  - Refactored general function structure

### Findologic

- `FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService`
  - Changed constructor signature
  - Removed `handleResult()`
- `FINDOLOGIC\FinSearch\Findologic\Api\SortingService`
  - Changed constructor signature
  - Removed some legacy functionality
- `FINDOLOGIC\FinSearch\Findologic\IntegrationType`
  - Moved to shopware6-common
- `FINDOLOGIC\FinSearch\Findologic\MainVariant`
    - Moved to shopware6-common

### Utils

- Removed some functions or moved them to `FINDOLOGIC\Shopware6Common\Export\Utils\Utils`:
  - `calculateUserGroupHash()`
  - `cleanString()`
  - `removeControlCharacters()`
  - `removeSpecialChars()`
  - `addProductAssociations()`
  - `addVariantAssociations()`
  - `addChildrenAssociations()`
  - `multiByteRawUrlEncode()`
  - `buildUrl()`
  - `isEmpty()`
  - `buildCategoryPath()`
  - `getCategoryBreadcrumb()`
  - `getEncodedUrl()`
  - `flat()`
  - `flattenWithUnique()`
