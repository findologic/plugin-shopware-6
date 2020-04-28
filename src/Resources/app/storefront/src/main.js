/** Import JavaScript plugin classes */
import FilterCategorySelect from './js/filter-category-select.plugin';

/** Register plugins in the plugin manager */
const PluginManager = window.PluginManager;
PluginManager.register('FilterCategorySelect', FilterCategorySelect, '[data-filter-category-select]');

if (module.hot) {
    module.hot.accept();
}
