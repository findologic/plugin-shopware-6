/** Import JavaScript plugin classes */
import FilterCategorySelect from './js/filter-category-select.plugin';
import FilterPropertySelect from './js/filter-property-select.plugin';
import FilterSliderRange from './js/filter-slider-range.plugin';
import FlListingPlugin from './js/listing/listing.plugin';

/** Register plugins in the plugin manager */
const PluginManager = window.PluginManager;

PluginManager.register('FilterCategorySelect', FilterCategorySelect, '[data-filter-category-select]');
PluginManager.register('FilterSliderRange', FilterSliderRange, '[data-filter-slider-range]');

PluginManager.override('Listing', FlListingPlugin, '[data-listing]');
PluginManager.override('FilterPropertySelect', FilterPropertySelect, '[data-filter-property-select]');

if (module.hot) {
    module.hot.accept();
}
