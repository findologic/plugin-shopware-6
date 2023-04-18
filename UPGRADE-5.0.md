# Upgrade from 4.x to 5.x

Changes: [4.0.3...5.0.0](https://github.com/findologic/plugin-shopware-6/compare/4.0.3...5.0.0)  
Changelog: [5.0.0 Release](https://github.com/findologic/plugin-shopware-6/releases/tag/5.0.0)

This file is **irrelevant** for you in case you do **not have a plugin extension**.

---

This file should help you upgrade from 4.x 5o 4.x, by providing you with
information that you are going to need, in case you have an extension plugin that
overrides or implements any classes of the main plugin.  
Information about private methods won't be preserved.

## Deprecations

No depracations were removed within this version.

## Changes

### General

- Upgraded code style to PHP 8.1
  - Improved typehinting
- Most of the member variables are now `readonly`

### Controller

- `FINDOLOGIC\FinSearch\Controller\ExportController`
  - Added member variable `$salesChannelProductRepository`
  - Refactored function structure

### Export

- `FINDOLOGIC\FinSearch\Export\Adapters\PriceAdapter`
  - Changed constructor signature
- `FINDOLOGIC\FinSearch\Export\Search\ProductSearcher`
  - Changed constructor signature
- `FINDOLOGIC\FinSearch\Export\Services\DynamicProductGroupService`
  - Changed constructor signature

### Findologic

- Moved all classes from `FINDOLOGIC\FinSearch\Findologic\Response\Xml21` to `FINDOLOGIC\FinSearch\Findologic\Response\Json10`
- `FINDOLOGIC\FinSearch\Findologic\Response\Xml21ResponseParser` was replaced with `FINDOLOGIC\FinSearch\Findologic\Response\Json10ResponseParser`
- Files were adapted to make use of our JSON API endpoint

- `FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler`
  - Changed order of constructor arguments

### Resources

- Templates have been updated to accommodate the changes within Shopware
