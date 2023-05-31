# 5.0.1

* [SW-799] Fixed a bug where keywords from a different language were exported additionally.
* Add support for 6.5.1.x

# 5.0.0

- This version is a major release, which may cause breaking changes, in case you have installed an extension plugin.
- Before upgrading, read our upgrade guide.
- Be aware, that this version only supports the shop versions starting from v6.5.0
- [SW-745] Switched from XML to JSON for internal API requests.
- [SW-791] Template changes within SW 6.5 have been applied to extended template files.
- [SW-793] Upgraded PHP code level to 8.1.
- [SW-631] Variants and RRP are now exported as separate tags.
- [SW-797] Support for variants within the product listing.
- [SW-784] The internal test build was migrated to a Symfony Flex setup.
- [SW-721] Code structure for internal parameters was improved.
- [SW-795] Fixed a bug where products within a dynamic product group of another sales channel were exported.

# 4.0.4

* [SW-799] Fixed a bug where keywords from a different language were exported additionally.

# 4.0.3

* [SW-788] Fixed a bug where invalid filter values on navigation pages were not disabled
* [SW-789] Fixed a bug that caused generic URLs to be exported for SEO Urls without a "/"

# 4.0.2

* [SW-777] Fixed a bug that caused errors for range slider values with more than 2 decimal places.
* [SW-779] Fixed a bug that caused the import to fail with special UTF-8 characters in the description.
* [SW-786] Fixed a bug that cause an exception for some newly setup shops.

# 4.0.1

- [SW-780] A bug was fixed, where supported Shopware versions were declared incompatible during installation.
- [SW-783] A bug was fixed, which lead to version conflicts during the installation via Composer.

# 4.0.0

- This version is a major release, which may cause breaking changes, in case you have installed an extension plugin.
- Before upgrading, read our upgrade guide.
- Be aware, that this version only supports the shop versions starting from v6.4.6.0
- [SW-582] Created a separate route to cache Dynamic Product Groups.
- [SW-736] Added support for Advanced Pricing.
- [SW-747] Dropped support for 6.2.x, 6.3.x and versions up to 6.4.5.1.
- [SW-749] Upgraded PHP code level from 7.2 to 7.4.
- [SW-752] Improved code readability by replacing container injections with service injections.
- [SW-761] Implemented export using structure from our internal shopware6-common library.
- [SW-765] Improved performance by only requesting variant properties and categories not assigned to the main product.
- [SW-768] Improved performance by refactoring dynamic product group caching and reading.
- [SW-770] Changed plugin configuration of container classes to container selectors.
- [SW-772] The properties of variants defined within "Generate variants" are now exported.
- [SW-773] Fixed variants with the same categories as the main product.
- [SW-774] Uninstall existing extension plugin when updating to new major version.
- [SW-764] Fixed a bug that caused the sliders of range sliders to not show without a search query.

# 3.1.3
- [SW-766] Make container classes configurable for all integration types.

# 3.1.2
- [SW-651] Scripts for the range slider are only loaded on search and navigation pages.
- [SW-712] The limits of the range sliders are now updated, when the values change by selecting other filter values.
- [SW-763] The attribute 'cat_url' is now exported for all integration types.
- [SW-753] A bug was fixed, where an exception occurred for invalid main products with valid variants.

# 3.1.1
- [SW-737] Filter values of color picker should be selectable by clicking on the value next to the tile.
- [SW-739] Color picker images must be shown for selected colors.
- [SW-740] Ignore invalid SEO Urls in export.
- [SW-746] There should be no export error when no canonical SEO url exists.
- [PH-719] Add 6.4.14.0 to the tested versions in our internal test runner.

# 3.1.0
- [SW-659] Introduced a new route for export debug information ('/findologic/debug')
- [SW-442] Add linting for the JavaScript files.
- [PH-672] Add 6.4.13.0 to the tested versions in our internal test runner.
- Downgrade the used composer version in our internal test runner.

# 3.0.0
- This version is a major release, which may cause breaking changes, in case you have installed an extension plugin.
- Before upgrading, read our upgrade guide.
- [SW-619] Fixed a performance issue for products with hundreds or thousands of variants.
- [SW-664] Introduced new adapter classes to the export.
- [SW-724] Improved the extension possibilities by creating adapters for unused XML tags.
- [SW-666] Products are now skipped, when some required data is not set.
- [SW-704] Added the variant information to the new adapter classes.
- [SW-727] Correctly handle the cheapest variant configuration for the new export.
- [SW-730] Improved the extension possibilities by splitting up a service into two.
- [SW-728] Made some further performance improvements within the new export.
- [SW-705] Implemented support for old extension plugins.
- [SW-733] Improved the extension possibilities by adding an event after a product is processed.
- [SW-732] Define an upgrade guide for the new release.
- [SW-700] Fixed a bug that caused the manufacturer filter not being shown when previously selecting a manufacturer in the Smart Suggest.
- [SW-591] Fixed a bug that caused the category filter not being disabled when no filter value was available.
- [SW-729] Add new minor versions to our internal test runner.
- [PH-657] Add 6.4.12.0 to the tested versions in our internal test runner.
- Fixed the internal test runner for SW version 6.4.9.0.

# 2.8.2
- [SW-715] Fixed a bug that caused an error due to incompatible filter values being passed by other plugins.
- [SW-716] Fixed a bug that caused the price filter being selected automatically when having product prices with more than two decimals.
- [SW-720] Fixed a bug that caused variant properties not being available on product listing pages.
- [SW-722] Fixed a bug that caused Direct Integration not working on navigation pages after using the mobile Smart Suggest.
- [SW-718] Update dependencies guzzlehttp/psr7 and minimist.

# 2.8.1
- [SW-689] Fixed a bug that caused available variants not being exported because the main product is not available.
- [SW-701] Fixed a bug that caused unavailable filter values not being deactivated.
- [SW-702] Fixed a bug that caused variants with price zero to be considered as cheapest variant during export.
- [SW-703] Fixed a bug that caused sidebar filters being displayed differently to Shopware default.
- [SW-708] Fixed a bug that caused products with same create time being sorted inconsistently during export.
- [SW-709] Fixed a bug that caused the filter button not being shown on mobile.
- [SW-707] Our internal test runner runs on Node 14 when the Shopware version isn't compatible with Node 16.

# 2.8.0
- [SW-695] The plugin configuration now offers selection of cross-selling categories for more than 500 categories.
- [SW-694] Fixed a bug that caused Dynamic Product Groups not to be excluded by cross-selling category setting.
- [SW-699] Fixed a bug that caused cross-selling cateogries not being suggested in some cases.
- [SW-698] Fixed a bug that caused the Shopware version not to be properly detected when using a special Shopware installation.

# 2.7.1
- [SW-696] Fixed a bug that caused products not to be displayed when using pagination on navigation pages.

# 2.7.0
- [SW-644] Exported categories and cat_urls now also include data from non-main variants.
- [SW-685] Third-Party plugins can now easily listen to specific sorting options and can manipulate the requests to the Findologic API accordingly.
- [SW-683] Fixed a bug that caused flickering on Direct Integration category pages, when many JavaScript resources have been loaded before Findologic JavaScript resources could be loaded.
- [SW-690] Fixed a bug that caused products not to be exported that didn't have a manufacturer assigned.
- [SW-691] Fixed a bug that caused internal additional listing requests to be blocked.
- [SW-693] Fixed a bug that caused data of inactive and variants out-of-stock to be exported.
- [SW-688] Fixed a bug that caused some routes to use MySQL instead of ElasticSearch for sales channels where Findologic was not active.
- [SW-692] Fixed a bug that caused a no-result on category listings with Dynamic Product Groups.
- [SW-687] Added Shopware 6.4.8.1 to the test matrix.

# 2.6.1
- [SW-677] HTML encoded attribute values are now automatically decoded in the export.
- [SW-569] The option to enable Findologic on category pages, has been moved to the API section.
- [SW-678] Fixed a bug that caused console errors on Direct Integration pages, where the URL did not contain a query parameter.
- [SW-624] Fixed a bug that would cause an error if the Shopware ElasticSearch bundle is not available.
- [SW-681] Fixed a bug that caused the Shopware version not to be properly detected on some Shopware installations.

# 2.6.0
- [SW-673] Improved performance on category pages, by improving the way the plugin gets the current category.
- [SW-633] Exported keywords now contain Shopware "Search Keywords", instead of configured "Tags".
- [SW-674] Fixed a bug that caused console errors on Direct Integration pages, that were caused by Shopware listing updates.

# 2.5.0
- [SW-613] The configuration now has a new "Export" section, where you can choose which variant should be exported to Findologic as "main variant". Options are "Shopware default", "Main-/Parent product", "Cheapest variant".
- [SW-589] The dropdown for choosing the language in the configuration, will now only show languages that have a URL associated to them.
- [SW-672] Fixed a bug that caused the plugin to send too many requests to the Findologic API on category pages, which negatively impacted the performance on these pages.
- [SW-671] Fixed a bug that broke the build, due to usage of deprecated usage of Composer 1 classes.
- [SW-668] Added Shopware 6.4.7.0 to the test matrix.

# 2.4.1
- [SW-669] Fixed a bug that caused an error on all listing pages where Findologic has not been active.

# 2.4.0
- [SW-601] Requests to the Findologic API now include the shopsystem and the shop version.
- [SW-662] Fixed a bug that caused the export to fail, if the configured main variant had been set to inactive for the exporting sales channel.
- [SW-663] Fixed a bug that caused products not to be exported, if they had configured a custom field containing "0".
- [SW-612] Fixed a bug that caused the product price to be exported wrong, in case the exporting sales channel had configured a non-default currency.
- [SW-632] Fixed a bug that caused the pagination at the bottom to be shown, when only one pagination page existed, which was inconsistent with the Shopware standard.

# 2.3.0
- [SW-567] The category filter has been improved and received a major UI/UX improvement.
- [SW-660] The export will now respect the configured main variant, if set.
- [SW-614] Fixed a bug that caused the configuration option not to show up. Now there is an "Open extension" option available.
- [SW-661] Fixed a bug that caused range-slider filters to be selected after deselecting previously selected filters.
- [SW-650] Fixed a bug that caused third-party listing criteria changes to cause an error.
- [SW-658] Added Shopware 6.4.6.0 to the test matrix.

# 2.2.1
- [SW-657] Fixed a bug that caused sorting by release date not to work properly.
- [SW-649] Fixed a bug that caused products not to be exported, when they had multi-select custom-fields configured, which contained empty values.
- [SW-653] Fixed a bug that could cause wrong integration types, when a sales channel had multiple shopkeys for different languages set.
- [SW-656] Fixed a bug that caused the export of nested categories to also be exported without a category path.
- [SW-654] Fixed a bug that caused the sorting option "Topseller" to be shown twice on category pages.
- [SW-652] Added Shopware 6.4.5.1 to the test matrix.

# 2.2.0
- [SW-648] Fixed a bug that caused promotion images to take up the full width of the viewport.
- [SW-595] Exported categories and category urls are now generated recursively, and filter names will no longer get their filter name sanitized for Direct Integration.
- Please ensure that filter names containing special characters are properly configured in the filter-configuration after updating.
- [SW-609] The Shopware 6 plugin release is now automated.
- [SW-645] Added Shopware 6.4.4.0 to the test matrix.

# 2.1.2
- [SW-634] Fixed a bug that caused range-slider filters to appear multiple times on mobile.
- [SW-635] Fixed a bug that caused products to be skipped, when a third-party plugin added a custom-field, which holds data in a multidimensional array format.
- [SW-638] Fixed a bug that caused the disappearance of the first range-slider, if the result contained more than one range-slider filters.
- [SW-636] Added Shopware 6.4.3.0 to the test matrix.
- [SW-639] Restructured and updated README.md to include a proper first-installation guide for development.
- [SW-641] Fixed the build, which was failing due to conflicting unit-test Trait methods.

# 2.1.1
- [SW-620] Fixed a bug that would cause Findologic interfering with the /suggest route.
- [SW-627] Fixed a bug that caused some Shopware services such as the API import to fail, as the plugin provided invalid/incomplete DAL relations.
- [SW-617] Fixed a bug that caused the disappearance of the pagination on category pages in case the Shopware cache provided the result.
- [SW-621] Fixed a bug that caused an error on category pages, when using Shopware version 6.3.2.x.
- [SW-618] Fixed a bug that would cause Findologic interfering with the listings on the homepage.
- [SW-623] Added Shopware 6.4.2.1 to our test matrix.

# 2.1.0
- [SW-599] Searching for a variant-specific ordernumber, will cause the searched variant to be shown in the listing, instead of the main product.
- [SW-606/SW-504] Range-Slider filters now contain a slider below the input fields.
- [SW-600] API integrations now support personalization via pushAttrib. In case there are any hidden pushAttrib input fields, these will be sent directly to the Findologic-API.
- [SW-607] Fixed a bug that caused console errors, which were caused by non-existing range-slider.css styles.
- [SW-616] Fixed a bug that caused filters to be overridden on category pages, even if Findologic had been disabled on category pages.
- [SW-610] Updated the GitHub Actions build to use Shopware 6.4.0.0 instead of 6.4.0.0-RC1.

# 2.0.2
- [SW-590] Fixed a bug that caused products having no/incorrect image on search/navigation pages, when the main product didn't have any images assigned.
- Note: With this change, products which have Fan out properties in product list configured, are exported as separate products.
- [SW-605] Fixed a bug that resulted in an error on search/navigation pages, when the Shopware version could not be properly detected.
- [SW-604] Added support for Shopware 6.3.5.3.
- [SW-497] Added support for Shopware 6.4.0.0.

# 2.0.1
- [SW-596] The performance for fetching the sales frequency from products, has been improved.
- [SW-581] The sales frequency now only includes orders made the last month.
- [SW-583] Fixed minor bugs in the plugin configuration.
- Shopkeys which have been added in the same browser session, could no longer be removed.
- Switching to another Sales Channel and entering a removed shopkey, caused an duplicated shopkey error message.

# 2.0.0
- This version is a major release, which may cause breaking changes, in case you have installed an extension plugin.
- Before upgrading, read our upgrade guide.
- [SW-509] Added the possibility to add configurations for each language in a Sales Channel.
- [SW-521 & SW-578] Filter values which would lead to no-results, will now get disabled, when the Shopware setting "Disable filter options without results" is active.
- [SW-481] It is now possible to add the "productId" parameter to the export. It will return the given product as XML format. If the product can not be exported a JSON is returned, containing all errors why the product couldn't be exported.
- [SW-498] Product list prices are now exported as properties. Property names are old_price and old_price_net.
- [SW-558] Configuring a product as a product promotion, will now export a property called product_promotion, which contains either Yes or No.
- [SW-592] Support for Shopware 6.1 has been dropped.
- [SW-540] The language dropdown in the configuration now only shows the languages assigned to the sales channel instead of all available languages.
- [SW-542] The shopkey field in the configuration is now unique. This means the same shopkey can only be set for one combination of language and sales channel.
- [SW-554] A loading animation is now shown in the administration plugin configuration, when changing the sales channel, or the language setting.
- [SW-512] Trying to install the plugin, will now result in an error if the Shopware version is not compatible with the plugin.
- [SW-561] The main product image will now use the first thumbnail, which is greater or equal to 600px, instead of the full-sized image.
- [SW-547] The CompatibilityLayer classes for Shopware versions 6.2, 6.3.1 and 6.3.2 have been removed. They have been generalized by the FindologicService service. It includes a separate PaginationService and a SortingService.
- [SW-575] Fixed a bug that caused the export of old SEO URLs, which have been already considered as deleted by Shopware.
- [SW-576] Fixed a bug that caused the export of cat_urls not to take the domain's path into account.
- [SW-580] Fixed a bug that would cause an error on pages that manually call the listing request route of the ProductListingFeaturesSubscriber.
- [SW-579] Fixed a bug that would cause the export of thumbnails in all various sizes. One thumbnail is now exported per media.
- [SW-585] Fixed a bug that caused filters not being shown on navigation pages, when using Shopware greater or equal to 6.3.4.0.
- [SW-586] Fixed a bug that caused filters not being properly disabled, when a filter didn't have any available filter values.
- [SW-520] Fixed a bug that caused products not to be exported, when their main variant has been inactive.
- [SW-543] Fixed a bug that caused product URLs to be exported in the wrong translation. This only affected products that had no SEO URLs associated.

# 1.5.3
- [SW-574] Shopware 6.3.5.0 is now compatible.

# 1.5.2
- [SW-551] Fixed a bug that caused major performance issues, when using vendor image and color image filters.
- [SW-556] Fixed a bug that caused the configured products-per-page setting to only work when using values lower or equal to 24.
- [SW-557] Fixed a bug in the export, which caused higher memory consumption, when products had many orders.
- [SW-559] Fixed a bug that would cause Smart Suggest category and vendor clicks to no longer work, when Elastic Search has been enabled.
- [SW-562] Fixed a bug that caused the sorting to display an incorrect value for older Shopware versions.
- [SW-566] Fixed a bug that would cause an error, if a third party plugin manually called the handleResult method of the product listing subscriber.

# 1.5.1
- [SW-550] Fixed a bug that caused the sorting dropdown not to update properly, after selecting an option. This affected Shopware versions above or equal 6.3.3.0.

# 1.5.0
- [SW-539] Topseller sorting is now supported.
- [SW-546] Custom-Fields of type multi-selection are now supported.
- [SW-544] Fixed a bug that caused a manipulation of the Shopware Criteria, even on pages where Findologic should not have been active (e.g. checkout, etc.)
- [SW-545] Fixed a bug that would cause products not to be exported, when one or more attribute values contained more characters than allowed by Findologic.

# 1.4.0
- [SW-357] Dynamic Product Groups (formerly known as Product Streams) are now supported in the export. When they're assigned to a category, all products inside of it, will be automatically assigned to this category.
- [SW-536] Fixed a bug that caused category pages for API not to work properly, when the name of the category had a space at the end.

# 1.3.2
- [SW-525] Fixed a bug that caused the Shopware autocomplete to be shown alongside the Findologic Smart Suggest.
- [SW-527] Fixed a bug that caused the pagination on category pages to not display properly, when Findologic was active on category pages.
- [SW-532] Fixed a bug that caused categories and cat_urls not to be exported, when their name has not been translated to the default application language.
- [SW-534] Fixed a bug that caused double slashes in the exported product URL, when the domain name contained a slash at the end.
- [SW-529] Fixed a bug that caused Customer Groups not to be respected in the export and for API search requests.

# 1.3.1
- [SW-475] Using a Shopping Guide and submitting it, will now display a info message for the Shopping Guide. Message: "Search results for <shopping-guide-name> (<hits> hits)".
- [SW-513] Fixed a bug that caused the SEO URL translation to be ignored. Now SEO URLs are exported, based on their language.
- [SW-516] Fixed a bug that caused a no-results page for products on the home page, when Findologic was active.
- [SW-522] Fixed a bug that caused products not to be exported, if they had a custom field assigned, which only contained special characters.
- [SW-502] The plugin now respects the product limit, when a third party plugin tries to override it.
- [SW-515] Our internal library "Findologic API" has been upgraded to 1.6.x. This results in a minor performance improvement.
- [SW-523] Shopware 6.3.3.0 is now supported.

# 1.3.0
- [SW-428] Cross-Selling categories can now be configured. Similar to our Shopware 5 plugin, configured categories are excluded from the export.
- [SW-466] Filters can now be configured on the left side of search result pages.
- [SW-466] The configuration page has been restructured to only show configurations for the used integration type.
- [SW-496] Fixed a bug that caused variations to be shown in the search result, when Findologic has been inactive for the used sales channel.
- [SW-501] Fixed a bug that caused Smart Suggest category and vendor clicks not to work, when the storefront has been hosted on a sub-path like https://your-shop.com/de.
- [SW-500] Fixed a bug that caused categories to be exported, even when they were not assigned to the sales channel.
- [SW-483] Fixed a bug that would cause the export to fail, when any fields contained empty values.
- [SW-503] Shopware 6.3.2.0 is now compatible.

# 1.2.0
- [SW-453] Configured custom-fields are now exported as attributes/filters.
- [SW-484] Properties which are "non-filterable", are no longer exported as attributes/filters. Instead they're exported as properties. Filterable properties, are still exported as attributes/filters.
- [SW-482] Fixed a bug that caused the search result site not to render properly, when the lowest price value of a product has been 0.
- [SW-485] Fixed a bug that caused no results, after selecting any filter, when the rating filter had same min/max values.
- [SW-467] Fixed a bug that caused an error when selecting a filter from the Smart Suggest, which was not available as filter in the filter configuration.

# 1.1.0
- [SW-426/SW-459] Rating filters are now supported. They are shown as such, when the filter type is configured as range-slider in the [filter configuration](https://account.findologic.com/#/app/filter-configuration/search).
- [SW-430] Added support for Promotions on navigation. They can be configured in our [account](https://account.findologic.com/#/app/filter-configuration/search).
- [SW-473] Shopware 6.3.x.x is now supported.
- [SW-411] Boolean values are exported in their proper language (Yes/No) instead of 0/1.
- [SW-471] Fixed a bug, that caused Shopware filters not to work properly, when Findologic was disabled on category pages.
- [SW-469] Fixed a bug, that caused canonical product URLs not to be exported as expected.

# 1.0.1
- [SW-465] Fixed a bug that caused the pagination at the bottom of the search results not to render.
- [SW-451] Fixed a bug that caused filter values to conflict with each other.
- [SW-463] Fixed a bug that caused filters not to open on category pages, when they contain special characters. This only affected filters if they are shown on the left side.
- [SW-462] Fixed a bug that caused the export to not work properly when another plugin was overriding the Shopware\Storefront\Framework\Routing\Router class.
- [SW-468] Fixed a bug that caused an exception when the HttpCache is enabled for Shopware >= 6.2.

# 1.0.0
- Supports both Direct Integration and API Integration.
- Same features as the [Findologic Shopware 5 plugin](https://store.shopware.com/fin1848466805161f/findologic-suche-navigation.html).
