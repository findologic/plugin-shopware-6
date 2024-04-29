window.PluginManager.register('FilterCategorySelect', () => import('./js/filter-category-select.plugin'), '[data-filter-category-select]');
window.PluginManager.register('FilterSliderRange', () => import('./js/filter-slider-range.plugin'), '[data-filter-slider-range]');

window.PluginManager.override('Listing', () => import('./js/listing/listing.plugin'), '[data-listing]');
window.PluginManager.override('FilterPropertySelect', () => import('./js/filter-property-select.plugin'), '[data-filter-property-select]');

if (module.hot) {
    module.hot.accept();
}
