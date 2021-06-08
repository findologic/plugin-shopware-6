# FINDOLOGIC Shopware 6 Plugin

[![Build Status](https://github.com/findologic/plugin-shopware-6/workflows/PHPUnit/badge.svg?branch=main)](https://github.com/findologic/plugin-shopware-6/actions)

## Table of Contents

1. [Installation](#installation)
1. [Libraries](#libraries)
1. [Export customization](#export-customization)
1. [Development](#development)
   1. [Developing custom JavaScript plugins](#developing-custom-javascript-plugins)
   1. [Running tests locally](#running-tests-locally)
1. [Deployment and Release](#deployment-and-release)
1. [Test Shopware release candidates](#test-shopware-release-candidates)

## Installation

Installing the FINDOLOGIC plugin is as simple as installing any other plugin.

1. Go to your shop administration (https://example.com/admin).
1. Upload the FINDOLOGIC plugin in Settings > System > Plugins > Upload plugin.
1. Install and activate the uploaded plugin.
1. Open the plugin configuration and enter your "Shopkey".
1. Start an import - or wait for a nightly import.
1. Set the plugin in the plugin configuration to "Active".

You may need to clear the cache after setting your configuration.

## Libraries

We are using some of our libraries that are especially relevant for this and other plugins.
Note that these libraries already come with the plugin itself, so you do not need to
install them yourself.

* [findologic/libflexport](https://github.com/findologic/libflexport) Helps generating
 the shop's data feed aka. export. We use it to generate an XML based on the product data
 of the shop.
* [findologic/findologic-api](https://github.com/findologic/findologic-api) Handles requests
 to FINDOLOGIC. This includes everything from sending query parameters like selected filters,
 output attributes, to parsing the response with response objects.

## Export customization

In some cases you may want to export additional, custom export data. To still provide you
plugin updates, we have an extension plugin. It overrides logic of the base plugin to reflect
your own logic.

Use the [FINDOLOGIC Shopware 6 extension plugin](https://github.com/findologic/plugin-shopware-6-extension) to customize your export. There are already pre-defined examples, that
guide you on how you can customize certain entities, like attributes or properties.

## Development

### Developing custom JavaScript plugins
- Check out the 
[offical guide](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/extend-core-js-storefront-plugin)
on how to extend js storefront plugin.
- Create your plugin files inside 
`src/Resources/app/storefront/src/js/[your-plugin-name].plugin.js`
- [Register your extended plugin](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/extend-core-js-storefront-plugin#register-your-extended-plugin)
- For a development build, use `./psh.phar storefront:dev`
- For a production build, use `./psh.phar storefront:build`

##### Note: 
The build commands will create a minified JS file in `src/Resources/app/storefront/dist/storefront/js/[plugin-name].js`. 
Before committing ensure that all files were built and added to your commit. Make sure to also commit the minified
 JavaScript files.

### Running Tests locally

In order to run tests locally you first need to clone the official
[`shopware/development`](https://github.com/shopware/development) repository.

Before continuing make sure that you have a running MySQL instance. Create a user named
`app` with password `app`, which should have privileges to create and modify all
databases.

Now run `./psh.phar init`. In order to run this command successfully you may require certain [packages that are
required by Shopware](https://docs.shopware.com/en/shopware-platform-dev-en/getting-started/requirements).

After that initialize the test database by running `./psh.phar init-test-databases`.
Move the plugin folder inside of `custom/plugins` and make sure that you have write permissions so you can
edit the plugin inside of the `custom/plugins` folder. If you haven't already, run `composer dump-autoload`.

That's basically it. When running tests just do not forget to add `phpunit.xml.dist` as default configuration file.

## Deployment and Release
Before starting the deployment make sure that a release is already created.

1. Run `git fetch` and ensure that the release tag is available locally. Make sure
 that the file `composer.json` contains the correct version constraint.
1. Run `composer release`, which will build a release `FinSearch-x.x.x.zip` file.
1. Upload this version to Google Drive `Development/Plugins/Shopware/Shopware 6 DI & API Plugin` and move the old
 version to `alte Versionen`.
1. Go to https://account.shopware.com and login. Go to
 `Manufacturer area > Plugins > Shopware 6 plugins > FINDOLOGIC Search & Navigation` and select *Versions*. Click
 on *Upload new version* and fill out all necessary fields. In the second step mark the plugin as compatible
 for Shopware 6.0 and newer. Last but not least upload the plugins' zip file and mark all
 required checkboxes.
1. Once the release is available require an *automatic code review*.
1. Notify everyone at Basecamp that the new release is available.

## Test Shopware release candidates

Access the application container
```
./psh.phar docker:ssh
```

Use the branch for the next release
```
composer require shopware/platform:x.x.x.x-dev
```

Delete cache
```
./psh.phar cache
```

Execute install script
```
./psh.phar install
```
