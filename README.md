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
