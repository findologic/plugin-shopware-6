/**
 * @jest-environment jsdom
 */

import FilterCategorySelectPlugin from '../src/js/filter-category-select.plugin';
import ListingPlugin
    from '../../../../../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/plugin/listing/listing.plugin';

describe('filter-category-select.plugin.js', () => {
    let filterCategorySelectPlugin;

    beforeEach(() => {
        window.csrf = {
            enabled: false,
        };

        window.router = [];

        const mockElement = document.createElement('div');

        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        const mockElementSpan = document.createElement('span');
        mockElementSpan.classList.add('filter-multi-select-count');

        const checkboxSelector = document.createElement('input');
        checkboxSelector.classList.add('filter-category-select-checkbox');

        const mockElementButton = document.createElement('button');
        mockElementButton.classList.add('filter-panel-item-toggle');

        mockElement.appendChild(cmsElementProductListingWrapper);
        mockElement.appendChild(mockElementButton);
        mockElement.appendChild(mockElementSpan);
        mockElement.appendChild(checkboxSelector);

        document.body.appendChild(mockElement);

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => [],
                };
            },
            getPluginInstanceFromElement: () => {
                return new ListingPlugin(mockElement);
            },
        };

        filterCategorySelectPlugin = new FilterCategorySelectPlugin(mockElement);
    });

    afterEach(() => {
        filterCategorySelectPlugin = null;
    });

    test('filter category select plugin exists', () => {
        expect(typeof filterCategorySelectPlugin).toBe('object');
    });
});
