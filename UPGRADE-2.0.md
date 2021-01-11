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

### CompatibilityLayer

TLDR; All classes (except 6.1) in the `CompatibilityLayer` directory have been removed.

We removed all classes/services/controllers in the `CompatibilityLayer` directory. Reason for that is that maintaining
these separate classes for each and every version is very error prone and also hard to extend for plugin extensions.
At the same time they were also hard to debug, as you always needed to know which Shopware version is being used, in
order to begin debugging. In case you're wondering where all this code now is, the answer is in the proper classes.
As an example code that was in `src/CompatibilityLayer/Shopware631/Core` can now be found in `src/Core`. This results
in less duplication and overall easier readable code. Some classes for Shopware 6.1 still are there, since they're not
relevant for any other Shopware version, except this only version.

#### Shopware631

* `FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware631\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`
 has been removed. Logic has been centralized in `\FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService`.

#### Shopware632

* `FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware632\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`
  has been removed. Logic has been centralized in `\FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService`.
* `FINDOLOGIC\FinSearch\CompatibilityLayer\Shopware632\Storefront\Controller\SearchController`
  has been removed. Logic has been centralized in `\FINDOLOGIC\FinSearch\Storefront\Controller\SearchController`.

### Controller

The `ExportController` received a refactoring, which enables extensions to easier override
things like product associations, or the output format. That being said, we want
plugin developers no longer to override the `ExportController` directly.  
**Use the [new exposed services](#new-servicesclassesinterfaces) instead**.

* `FINDOLOGIC\FinSearch\Controller\ExportController`
  * The signature of method `ExportController::export()` has been updated to `ExportController::export(Request $request, ?SalesChannelContext $context): Response`.
  * Method `ExportController::getProductCriteria(?int $offset = null, ?int $limit = null)` has been replaced by
    `\FINDOLOGIC\FinSearch\Export\ProductService::buildProductCriteria()`.
  * Method `ExportController::getTotalProductCount()` has been replaced by `\FINDOLOGIC\FinSearch\Export\ProductService::getTotalProductCount()`.
  * Method `ExportController::getProductsFromShop()` has been replaced by `\FINDOLOGIC\FinSearch\Export\ProductService::searchVisibleProducts()`.

### Core

* `FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`
  * Does not extend `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber` anymore.
  * Implements `EventSubscriberInterface` instead.
  * The signature of method `ProductListingFeaturesSubscriber::__construct()` has been updated to `ProductListingFeaturesSubscriber::__construct(ShopwareProductListingFeaturesSubscriber $decorated, FindologicSearchService $findologicSearchService)`.
  * Almost all logic has been extracted to `FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService`.
* `FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Listing\ProductListingRoute`
  * The signature of method `ProductListingRoute::__construct()` has been updated to `ProductListingRoute::__construct(AbstractProductListingRoute $decorated, SalesChannelRepositoryInterface $productRepository, EventDispatcherInterface $eventDispatcher, ProductDefinition $definition, RequestCriteriaBuilder $criteriaBuilder, ServiceConfigResource $serviceConfigResource, FindologicConfigService $findologicConfigService, ?Config $config = null)`.
* `FINDOLOGIC\FinSearch\Core\Content\Product\SalesChannel\Search\ProductSearchRoute`
  * The signature of method `ProductSearchRoute::__construct()` has been updated to `ProductSearchRoute::__construct(AbstractProductSearchRoute $decorated, ProductSearchBuilderInterface $searchBuilder, EventDispatcherInterface $eventDispatcher, ProductDefinition $definition, RequestCriteriaBuilder $criteriaBuilder, ServiceConfigResource $serviceConfigResource, FindologicConfigService $findologicConfigService, ?Config $config = null)`.

### Exceptions

All exceptions have been moved to a proper namespace, depending on when they're thrown (e.g. export or search).
Some also have been removed as they're no longer needed.

* `FINDOLOGIC\FinSearch\Exceptions\AccessEmptyPropertyException` has been moved to `FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException`.
  * Now extends from `ProductInvalidException`.
* `FINDOLOGIC\FinSearch\Exceptions\ProductHasCrossSellingCategoryException` has been removed without replacement.
* `FINDOLOGIC\FinSearch\Exceptions\ProductHasNoAttributesException` has been moved to `FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoAttributesException`.
  * Now extends from `ProductInvalidException`.
* `FINDOLOGIC\FinSearch\Exceptions\ProductHasNoCategoriesException` has been moved to `FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException`.
  * Now extends from `ProductInvalidException`.
* `FINDOLOGIC\FinSearch\Exceptions\ProductHasNoNameException` has been moved to `FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException`
  * Now extends from `ProductInvalidException`.
* `FINDOLOGIC\FinSearch\Exceptions\ProductHasNoPricesException` has been moved to `FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException`
  * Now extends from `ProductInvalidException`.
* `FINDOLOGIC\FinSearch\Exceptions\UnknownCategoryException` has been moved to `FINDOLOGIC\FinSearch\Exceptions\Search\UnknownCategoryException`.
  * Now extends from `SearchException`.
* `FINDOLOGIC\FinSearch\Exceptions\UnknownShopkeyException` has been removed without replacement.

### Export

* `FINDOLOGIC\FinSearch\Export\HeaderHandler`
  * Header constants are now public.
  * The signature of method `HeaderHandler::getHeaders()` has been updated to `HeaderHandler::getHeaders(array $overrides = []): array`.
* `FINDOLOGIC\FinSearch\Export\XmlProduct`
  * The signature of method `XmlProduct::getXmlItem()` has been updated to `XmlProduct::getXmlItem(): ?Item`.
  * The signature of method `XmlProduct::buildXmlItem()` has been updated to `XmlProduct::buildXmlItem(?LoggerInterface $logger = null): void`.

### Resources

* `Resources/app/administration/src/module/findologic-module/components/findologic-config/findologic-config.html.twig`
  * The entire view has been updated to now work with our own configuration instead of the Shopware provided one.
* `Resources/app/administration/src/module/findologic-module/components/findologic-config/index.js`
  * Logic of methods `showAPIConfig` and `showDIConfig` have been simplified.
  * Added `integrationType`, which returns the integration type. The view manually computed this before.
* `Resources/app/administration/src/module/findologic-module/page/findologic-page/findologic-page.html.twig`
  * Similar to `findologic-config.html.twig`, this page has been updated entirely (including JS).
* `Resources/config/compatibility/latest/services.xml` logic has been moved to `Resources/config/services/services.xml`
* `Resources/config/compatibility/shopware61/services.xml`
  * Services `ProductListingFeaturesSubscriber` and `SearchController` have been moved to `Resources/config/services/decorators.xml`
* `Resources/config/compatibility/shopware631/services.xml` has been removed without replacement.
* `Resources/config/compatibility/shopware632/services.xml` has been removed without replacement.
* `Resources/config/config.xml` has been removed without replacement. With 2.0.0 we provide our own configuration page.
* `Resources/config/services.xml` has been split up to:
  * `Resources/config/services/services.xml` Contains all declared serviced by the plugin.
  * `Resources/config/services/decorators.xml` Contains all decorated services.
  * `Resources/config/services/subscribers.xml` Contains all subscribers.
  
### Storefront

* `FINDOLOGIC\FinSearch\Storefront\Controller\SearchController`
  * No longer extends from `Shopware\Storefront\Controller\SearchController`.
  * Now extends from `Shopware\Storefront\Controller\StorefrontController`.
  * The signature of method `SearchController::__construct()` has been updated to `SearchController::__construct(ShopwareSearchController $decorated, ?SearchPageLoader $searchPageLoader, FilterHandler $filterHandler, ContainerInterface $container)`.
* `FINDOLOGIC\FinSearch\Storefront\Page\Search\SearchPageLoader`
  * The signature of method `SearchPageLoader::__construct()` has been updated to `SearchPageLoader::__construct(GenericPageLoader $genericLoader, ?AbstractProductSearchRoute $productSearchRoute, EventDispatcherInterface $eventDispatcher, ?LegacySearchPageLoader $legacyPageLoader)`.

### Struct

* `FINDOLOGIC\FinSearch\Struct\Config`
  * The signature of method `Config::__construct()` has been updated to `Config::__construct(FindologicConfigService $systemConfigService, ServiceConfigResource $serviceConfigResource)`.
  * The signature of method `Config::initializeBySalesChannel()` has been updated to `Config::initializeBySalesChannel(SalesChannelContext $salesChannelContext)`.

### Subscriber

* `FINDOLOGIC\FinSearch\Subscriber\FrontendSubscriber`
  * The signature of method `FrontendSubscriber::__construct()` has been updated to `FrontendSubscriber::__construct(FindologicConfigService $systemConfigService, ServiceConfigResource $serviceConfigResource, ?Config $config = null)`.

## New Services/Classes/Interfaces

### Controller

* `FINDOLOGIC\FinSearch\Controller\FindologicConfigController` is a new controller for the administration
 configuration page. It is used to get and save the plugin configuration.

### Export

For easier extension, we have created several services to extend every bit of the export,
with as little effort as possible, while at the same time improving our code structure.  
We are planning to add more services for the export, when we add new features to it.

* `\FINDOLOGIC\FinSearch\Export\ProductService` is responsible for fetching the products from
 the database. Can be extended to:
  * Define exported products.
  * Define product associations.
* `\FINDOLOGIC\FinSearch\Export\SalesChannelService` is responsible for getting the relevant
 `SalesChannelContext` based on the given shopkey. Can be extended to:
  * Return a specific sales channel context based on pre-defined parameters.

---

* `FINDOLOGIC\FinSearch\Export\Definitions\XmlFields` contains a single public constant `KEYS`, which contains all
 available XML fields.
* `FINDOLOGIC\FinSearch\Export\Errors\ExportErrors` class which holds information about export errors.
* `FINDOLOGIC\FinSearch\Export\Errors\ProductError` holds information about errors relating a single product.
* `FINDOLOGIC\FinSearch\Export\Export` base class for exports. Builds an XmlExport or ProductIdExport depending on the given data.
* `FINDOLOGIC\FinSearch\Export\ProductIdExport` contains data about all errors for the product id export.
* `FINDOLOGIC\FinSearch\Export\XmlExport` builds the XML export response, which can be consumed by Findologic.

### Exception

To easier catch exceptions from a specific type, there are a few new exception base classes.

* `FINDOLOGIC\FinSearch\Exceptions\FindologicException\ExportException` all exceptions which are thrown
 in the export, derive from this class.
  * Extends from `FindologicException`.
* `FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductInvalidException` is thrown when a single product
 contains invalid information.
  * Extends from `ExportException`.
* `FINDOLOGIC\FinSearch\Exceptions\FindologicException` is the base class for all exceptions in the plugin. New
 exceptions will always derive from this class.
  * Extends from `\Exception`.
* `FINDOLOGIC\FinSearch\Exceptions\Search\SearchException` is thrown when something goes wrong while Findologic
 is handling the searched query.
  * Extends from `FindologicException`.

### Findologic

* `FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService` is responsible for sending requests to Findologic,
 parsing the response and making this data ready to consume by the views. This service can be easily extended 
 via the extension plugin.
* `FINDOLOGIC\FinSearch\Struct\Pagination\PaginationService` is responsible for handling the pagination parameters.
* `FINDOLOGIC\FinSearch\Findologic\Config\FinSearchConfigCollection` an `EntityCollection` containing Findologic plugin configurations.
* `FINDOLOGIC\FinSearch\Findologic\Config\FinSearchConfigDefinition` the definition for a configuration entity.
* `FINDOLOGIC\FinSearch\Findologic\Config\FinSearchConfigEntity` a single Findologic plugin configuration entity.
* `FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService` is a service, that allows easy access to Findologic plugin configuration.

### Logger

Logger is a new namespace, which currently only contains a product error handler. Since 2.0.0 the plugin now writes
all export logs into a separate file. This way export issues can be detected easier.

* `FINDOLOGIC\FinSearch\Logger\Handler\ProductErrorHandler` is a logger handler. We use it to either log the data into the log file
  or directly output the errors instead.
  * Implements `HandlerInterface`.

### Migration

* `FINDOLOGIC\FinSearch\Migration\Migration1605714035FinSearchConfig` is a migration, which introduces our own
 configuration table.
  
### Resources

* `Resources/app/administration/src/api/finsearch-config.api.service.js` is an api service, which allows easy communiation
 for fetching and updating the plugin configuration.
* `Resources/app/administration/src/init/finsearch.init.js` initializes essential instances for the plugin config page.

### Validators

* `FINDOLOGIC\FinSearch\Validators\ExportConfiguration` validates the given export query parameters.
