# 2.0.2
- Fixed a bug that caused products having no/incorrect image on search/navigation pages, when the main product didn't have any images assigned.
- Note: With this change, products which have Fan out properties in product list configured, are exported as separate products.
- Fixed a bug that resulted in an error on search/navigation pages, when the Shopware version could not be properly detected.
- Added support for Shopware 6.3.5.3.
- Added support for Shopware 6.4.0.0.

# 2.0.1
- The performance for fetching the sales frequency from products, has been improved.
- The sales frequency now only includes orders made the last month.
- Fixed minor bugs in the plugin configuration.
- Shopkeys which have been added in the same browser session, could no longer be removed.
- Switching to another Sales Channel and entering a removed shopkey, caused an duplicated shopkey error message.

# 2.0.0
- This version is a major release, which may cause breaking changes, in case you have installed an extension plugin.
- Before upgrading, read our upgrade guide.
- Added the possibility to add configurations for each language in a Sales Channel.
- Filter values which would lead to no-results, will now get disabled, when the Shopware setting "Disable filter options without results" is active.
- It is now possible to add the "productId" parameter to the export. It will return the given product as XML format. If the product can not be exported a JSON is returned, containing all errors why the product couldn't be exported.
- Product list prices are now exported as properties. Property names are old_price and old_price_net.
- Configuring a product as a product promotion, will now export a property called product_promotion, which contains either Yes or No.
- Support for Shopware 6.1 has been dropped.
- The language dropdown in the configuration now only shows the languages assigned to the sales channel instead of all available languages.
- The shopkey field in the configuration is now unique. This means the same shopkey can only be set for one combination of language and sales channel.
- A loading animation is now shown in the administration plugin configuration, when changing the sales channel, or the language setting.
- Trying to install the plugin, will now result in an error if the Shopware version is not compatible with the plugin.
- The main product image will now use the first thumbnail, which is greater or equal to 600px, instead of the full-sized image.
- The CompatibilityLayer classes for Shopware versions 6.2, 6.3.1 and 6.3.2 have been removed. They have been generalized by the FindologicService service. It includes a separate PaginationService and a SortingService.
- Fixed a bug that caused the export of old SEO URLs, which have been already considered as deleted by Shopware.
- Fixed a bug that caused the export of cat_urls not to take the domain's path into account.
- Fixed a bug that would cause an error on pages that manually call the listing request route of the ProductListingFeaturesSubscriber.
- Fixed a bug that would cause the export of thumbnails in all various sizes. One thumbnail is now exported per media.
- Fixed a bug that caused filters not being shown on navigation pages, when using Shopware greater or equal to 6.3.4.0.
- Fixed a bug that caused filters not being properly disabled, when a filter didn't have any available filter values.
- Fixed a bug that caused products not to be exported, when their main variant has been inactive.
- Fixed a bug that caused product URLs to be exported in the wrong translation. This only affected products that had no SEO URLs associated.

# 1.5.3
- Shopware 6.3.5.0 is now compatible.

# 1.5.2
- Fixed a bug that caused major performance issues, when using vendor image and color image filters.
- Fixed a bug that caused the configured products-per-page setting to only work when using values lower or equal to 24.
- Fixed a bug in the export, which caused higher memory consumption, when products had many orders.
- Fixed a bug that would cause Smart Suggest category and vendor clicks to no longer work, when Elastic Search has been enabled.
- Fixed a bug that caused the sorting to display an incorrect value for older Shopware versions.
- Fixed a bug that would cause an error, if a third party plugin manually called the handleResult method of the product listing subscriber.

# 1.5.1
- Fixed a bug that caused the sorting dropdown not to update properly, after selecting an option. This affected Shopware versions above or equal 6.3.3.0.

# 1.5.0
- Topseller sorting is now supported.
- Custom-Fields of type multi-selection are now supported.
- Fixed a bug that caused a manipulation of the Shopware Criteria, even on pages where Findologic should not have been active (e.g. checkout, etc.)
- Fixed a bug that would cause products not to be exported, when one or more attribute values contained more characters than allowed by Findologic.

# 1.4.0
- Dynamic Product Groups (formerly known as Product Streams) are now supported in the export. When they're assigned to a category, all products inside of it, will be automatically assigned to this category.
- Fixed a bug that caused category pages for API not to work properly, when the name of the category had a space at the end.

# 1.3.2
- Fixed a bug that caused the Shopware autocomplete to be shown alongside the Findologic Smart Suggest.
- Fixed a bug that caused the pagination on category pages to not display properly, when Findologic was active on category pages.
- Fixed a bug that caused categories and cat_urls not to be exported, when their name has not been translated to the default application language.
- Fixed a bug that caused double slashes in the exported product URL, when the domain name contained a slash at the end.
- Fixed a bug that caused Customer Groups not to be respected in the export and for API search requests.

# 1.3.1
- Using a Shopping Guide and submitting it, will now display a info message for the Shopping Guide. Message: "Search results for <shopping-guide-name> (<hits> hits)".
- Fixed a bug that caused the SEO URL translation to be ignored. Now SEO URLs are exported, based on their language.
- Fixed a bug that caused a no-results page for products on the home page, when Findologic was active.
- Fixed a bug that caused products not to be exported, if they had a custom field assigned, which only contained special characters.
- The plugin now respects the product limit, when a third party plugin tries to override it.
- Our internal library "Findologic API" has been upgraded to 1.6.x. This results in a minor performance improvement.
- Shopware 6.3.3.0 is now supported.

# 1.3.0
- Cross-Selling categories can now be configured. Similar to our Shopware 5 plugin, configured categories are excluded from the export.
- Filters can now be configured on the left side of search result pages.
- The configuration page has been restructured to only show configurations for the used integration type.
- Fixed a bug that caused variations to be shown in the search result, when Findologic has been inactive for the used sales channel.
- Fixed a bug that caused Smart Suggest category and vendor clicks not to work, when the storefront has been hosted on a sub-path like https://your-shop.com/de.
- Fixed a bug that caused categories to be exported, even when they were not assigned to the sales channel.
- Fixed a bug that would cause the export to fail, when any fields contained empty values.
- Shopware 6.3.2.0 is now compatible.

# 1.2.0
- Configured custom-fields are now exported as attributes/filters.
- Properties which are "non-filterable", are no longer exported as attributes/filters. Instead they're exported as properties. Filterable properties, are still exported as attributes/filters.
- Fixed a bug that caused the search result site not to render properly, when the lowest price value of a product has been 0.
- Fixed a bug that caused no results, after selecting any filter, when the rating filter had same min/max values.
- Fixed a bug that caused an error when selecting a filter from the Smart Suggest, which was not available as filter in the filter configuration.

# 1.1.0
- Rating filters are now supported. They are shown as such, when the filter type is configured as range-slider in the [filter configuration](https://account.findologic.com/#/app/filter-configuration/search).
- Added support for Promotions on navigation. They can be configured in our [account](https://account.findologic.com/#/app/filter-configuration/search).
- Shopware 6.3.x.x is now supported.
- Boolean values are exported in their proper language (Yes/No) instead of 0/1.
- Fixed a bug, that caused Shopware filters not to work properly, when Findologic was disabled on category pages.
- Fixed a bug, that caused canonical product URLs not to be exported as expected.

# 1.0.1
- Fixed a bug that caused the pagination at the bottom of the search results not to render.
- Fixed a bug that caused filter values to conflict with each other.
- Fixed a bug that caused filters not to open on category pages, when they contain special characters. This only affected filters if they are shown on the left side.
- Fixed a bug that caused the export to not work properly when another plugin was overriding the Shopware\Storefront\Framework\Routing\Router class.
- Fixed a bug that caused an exception when the HttpCache is enabled for Shopware >= 6.2.

# 1.0.0
- Supports both Direct Integration and API Integration.
- Same features as the [Findologic Shopware 5 plugin](https://store.shopware.com/fin1848466805161f/findologic-suche-navigation.html).
