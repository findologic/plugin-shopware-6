# FINDOLOGIC Shopware 6 Plugin


## Development

### Running Tests locally

In order to run tests locally you first need to clone the official
[`shopware/development`](https://github.com/shopware/development) repository.

Before continuing make sure that you have a running MySQL instance. Create a user named
`app` with password `app`, which should have privileges to create and modify all
databases.

Now run `./psh.phar install`. In order to run this command successfully you may require certain [packages that are
required by Shopware](https://docs.shopware.com/en/shopware-platform-dev-en/getting-started/requirements).

After that initialize the test database by running `./psh.phar init-test-databases`.
Move the plugin folder inside of `custom/plugins` and make sure that you have write permissions so you can
edit the plugin inside of the `custom/plugins` folder. If you haven't already, run `composer dump-autoload`.

That's basically it. When running tests just do not forget to add `phpunit.xml.dist` as default configuration file.

## Deployment and Release
Before starting the deployment make sure that a release is already created.

1. Run `git fetch` and ensure that the release tag is available locally. Make sure
 that the file `./FinSearchUnified/plugin.xml` contains the correct version constraint.
1. Remove the following folder/files:
   1. `/vendor`
   1. `/tests`
   1. `.gitignore`
   1. `.travis.yml`
   1. `phpcs.xml`
   1. `phpunit.xml.dist`
1. Run `composer install --no-dev`.
1. Create a zip file named `FinSearch-x.x.x.zip` and copy the entire folder in. Make sure to rename it to `FinSearch`.
1. Upload this version to Google Drive `Development/Modul-Entwicklung/Unified Module/Shopware 6` and move the old
 version to `alte Versionen`.
1. Go to https://account.shopware.com and login. Go to
 `Manufacturer area > Plugins > FINDOLOGIC Search & Navigation` and select *Versions*. Click
 on *Upload new version* and fill out all necessary fields. In the second step mark the plugin as compatible
 for Shopware 5.3 and newer. Last but not least upload the plugins' zip file and mark all
 required checkboxes.
1. Once the release is available require an *automatic code review*.
1. Notify everyone at Basecamp that the new release is available.
