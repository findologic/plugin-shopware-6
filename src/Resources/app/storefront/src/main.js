/** Import JavaScript plugin classes */
import FilterCategorySelect from './js/filter-category-select.plugin';
import '../node_modules/nouislider/distribute/nouislider.css';

const noUiSlider = require('@nouislider');

/** Register plugins in the plugin manager */
const PluginManager = window.PluginManager;
PluginManager.register('FilterCategorySelect', FilterCategorySelect, '[data-filter-category-select]');

// This code may be removed, but left there as an example. Once uncommented, it will create a range-slider
// above the page header.
// const slider = document.createElement('div');
// document.body.prepend(slider);
//
// noUiSlider.create(slider, {
//     start: [20, 80],
//     connect: true,
//     range: {
//         'min': 0,
//         'max': 100
//     }
// });

if (module.hot) {
    module.hot.accept();
}
