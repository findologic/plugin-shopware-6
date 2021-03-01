/** Import JavaScript plugin classes */
import FilterCategorySelect from './js/filter-category-select.plugin';
import FilterPropertySelect from './js/filter-property-select.plugin';
import FilterRange from './js/filter-range.plugin';

/** Register plugins in the plugin manager */
const PluginManager = window.PluginManager;
PluginManager.register('FilterCategorySelect', FilterCategorySelect, '[data-filter-category-select]');
PluginManager.override('FilterPropertySelect', FilterPropertySelect, '[data-filter-property-select]');
PluginManager.override('FilterRange', FilterRange, '[data-filter-range]');

if (module.hot) {
    module.hot.accept();
}
