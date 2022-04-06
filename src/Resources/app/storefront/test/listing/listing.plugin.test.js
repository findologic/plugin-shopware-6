/**
 * @jest-environment jsdom
 */

import FlListingPlugin from "../../src/js/listing/listing.plugin";

describe('listing.plugin,js', () => {
  let listingPlugin = undefined;
  let spyInit = jest.fn();
  let spyInitializePlugins = jest.fn();

  function setupListingPlugin() {
    const mockElement = document.createElement('div');
    const cmsElementProductListingWrapper = document.createElement('div');
    cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

    document.body.append(cmsElementProductListingWrapper);

    listingPlugin = new FlListingPlugin(mockElement);
  }

  beforeEach(() => {
    // create mocks
    window.csrf = {
      enabled: false
    };

    window.router = [];

    window.PluginManager = {
      getPluginInstancesFromElement: () => {
        return new Map();
      },
      getPlugin: () => {
        return {
          get: () => []
        };
      },
      initializePlugins: undefined
    };

    // mock listing plugins
    const mockElement = document.createElement('div');
    listingPlugin = new FlListingPlugin(mockElement);
    listingPlugin._registry = [];

    // create spy elements
    listingPlugin.init = spyInit;
    window.PluginManager.initializePlugins = spyInitializePlugins;
  });

  afterEach(() => {
    listingPlugin = undefined;
    spyInit.mockClear();
    spyInitializePlugins.mockClear();
    window.PluginManager.initializePlugins = undefined;
  });

  test('listing plugin exists', () => {
    expect(typeof listingPlugin).toBe('object');
  });

  test.each([
    '#navigation:search=&attrib%5Bcat_url%5D%5B0%5D=%2FKids%2F',
    '#search:search=blub&query=blub',
    '#suggest:suggest',
    '#navigation:attrib%5Bcat_url%5D%5B0%5D=%2Fwohnaccessoires%2F&count=24',
    '#search:count=24',
  ])('history must not be changed on Direct Integration page %s', (hash) => {
    setupListingPlugin();

    jest.spyOn(listingPlugin, 'refreshRegistry');
    window.location.hash = hash;

    listingPlugin._onWindowPopstate();

    expect(listingPlugin.refreshRegistry).not.toHaveBeenCalled();
  });

  test('history must be changed on regular non-Direct Integration pages', () => {
    setupListingPlugin();

    jest.spyOn(listingPlugin, 'refreshRegistry');
    window.location.hash = '#shopware';

    listingPlugin._onWindowPopstate();

    expect(listingPlugin.refreshRegistry).toHaveBeenCalled();
  });
});
