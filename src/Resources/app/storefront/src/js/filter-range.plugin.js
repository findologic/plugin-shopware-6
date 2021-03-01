import FilterRangePlugin from 'src/plugin/listing/filter-range.plugin';
import '../../node_modules/nouislider/distribute/nouislider.css';
import deepmerge from 'deepmerge';
import DomAccess from 'src/helper/dom-access.helper';

const noUiSlider = require('@nouislider');

export default class FlFilterRangePlugin extends FilterRangePlugin {

  static options = deepmerge(FilterRangePlugin.options, {
    sliderContainer: '.fl--range-slider',
  });

  _init() {
    super._init();
    this._inputMin = DomAccess.querySelector(this.el, this.options.inputMinSelector);
    this._inputMax = DomAccess.querySelector(this.el, this.options.inputMaxSelector);

    const slider = document.createElement('div');
    this._sliderContainer = DomAccess.querySelector(this.el, this.options.sliderContainer);
    this._sliderContainer.prepend(slider);

    noUiSlider.create(slider, {
      start: [this._inputMin.value, this._inputMax.value],
      connect: true,
      step: this.options.price.step,
      range: {
        'min': this.options.price.min,
        'max': this.options.price.max,
      },
    });

    // Binding signature
    slider.noUiSlider.on('update', this.onUpdateValues.bind(this));
    slider.noUiSlider.on('set', this._onChangeInput.bind(this));

  }

  onUpdateValues(values) {
    if (values[0] < this.options.price.min) {
      values[0] = this.options.price.min;
    }
    if (values[1] > this.options.price.max) {
      values[1] = this.options.price.max;
    }

    this._inputMin.value = values[0];
    this._inputMax.value = values[1];
  }
}
