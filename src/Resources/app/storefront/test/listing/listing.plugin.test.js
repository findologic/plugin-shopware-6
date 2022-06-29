/**
 * @jest-environment jsdom
 */

import ListingPlugin from 'src/plugin/listing/listing.plugin';

describe('listing.plugin,js', () => {
    let listingPlugin;
    const spyInit = jest.fn();
    const spyInitializePlugins = jest.fn();

    function setupListingPlugin() {
        const mockElement = document.createElement('div');
        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        document.body.append(cmsElementProductListingWrapper);

        const FlListingPlugin = require('../../src/js/listing/listing.plugin').default;

        listingPlugin = new FlListingPlugin(mockElement);
    }

    beforeEach(() => {
    // create mocks
        window.csrf = {
            enabled: false,
        };

        window.router = [];

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: (value) => (value === 'class' ? ListingPlugin : []),
                };
            },
            initializePlugins: undefined,
        };

        // mock listing plugins
        const mockElement = document.createElement('div');
        const FlListingPlugin = require('../../src/js/listing/listing.plugin').default;
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

    test('lastHash is set on initialization', () => {
        const expectedHash = '#initialHash';
        window.location.hash = '#initialHash';

        setupListingPlugin();

        expect(listingPlugin.lastHash).toBe(expectedHash);
    });

    test.each([
        { lastHash: '', hash: '#navigation:search=&attrib%5Bcat_url%5D%5B0%5D=%2FKids%2F' },
        { lastHash: '', hash: '#search:search=blub&query=blub' },
        { lastHash: '', hash: '#suggest:suggest' },
        { lastHash: '', hash: '#navigation:attrib%5Bcat_url%5D%5B0%5D=%2Fwohnaccessoires%2F&count=24' },
        { lastHash: '', hash: '#search:count=24' },
        { lastHash: '#suggest:suggest', hash: '' },
        { lastHash: '#search:count=24', hash: '' },
    ])('history must not be changed on Direct Integration page %s', ({ lastHash, hash }) => {
        setupListingPlugin();

        jest.spyOn(listingPlugin, 'refreshRegistry');
        window.location.hash = hash;
        listingPlugin.lastHash = lastHash;

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

    test('lastHash is changed on each check', () => {
        window.location.hash = '#initialHash';

        setupListingPlugin();

        expect(listingPlugin.lastHash).toBe('#initialHash');
        window.location.hash = '#newHash';

        listingPlugin._isDirectIntegrationPage();

        expect(listingPlugin.lastHash).toBe('#newHash');
    });
});
