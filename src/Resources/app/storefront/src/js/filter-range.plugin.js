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

    this.slider = document.createElement('div');
    this._sliderContainer = DomAccess.querySelector(this.el, this.options.sliderContainer);
    this._sliderContainer.prepend(this.slider);

    let start = this._inputMin.value ? this._inputMin.value : this.options.price.min;
    let end = this._inputMax.value ? this._inputMax.value : this.options.price.max;

    noUiSlider.create(this.slider, {
      start: [start, end],
      connect: true,
      step: this.options.price.step,
      range: {
        'min': this.options.price.min,
        'max': this.options.price.max,
      },
    });

    // Register events
    this.slider.noUiSlider.on('update', this.onUpdateValues.bind(this));
    this.slider.noUiSlider.on('set', this._onChangeInput.bind(this));
  }

  /**
   * @return {Object}
   * @public
   */
  getValues() {
    const values = {};

    this.validateMinInput();
    this.validateMaxInput();

    values[this.options.minKey] = this._inputMin.value;
    values[this.options.maxKey] = this._inputMax.value;

    return values;
  }

  /**
   * @param params
   * @public
   * @return {boolean}
   */
  setValuesFromUrl(params) {
    let stateChanged = false;
    Object.keys(params).forEach(key => {
      if (key === this.options.minKey) {
        this._inputMin.value = params[key];
        this.validateMinInput();
        stateChanged = true;
      }
      if (key === this.options.maxKey) {
        this._inputMax.value = params[key];
        this.validateMaxInput();
        stateChanged = true;
      }
    });

    return stateChanged;
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

  /**
   * @param id
   * @public
   */
  reset(id) {
    if (id === this.options.minKey) {
      this.resetMin();
    }

    if (id === this.options.maxKey) {
      this.resetMax();
    }

    this._removeError();
  }

  /**
   * @public
   */
  resetAll() {
    this.resetMin();
    this.resetMax();
    this._removeError();
  }

  validateMinInput() {
    if (!this._inputMin.value || this._inputMin.value < this.options.price.min) {
      this.resetMin();
    }
  }

  validateMaxInput() {
    if (!this._inputMax.value || this._inputMax.value > this.options.price.max) {
      this.resetMax();
    }
  }

  resetMin() {
    this._inputMin.value = this.options.price.min;
    if(this.slider) {
      this.slider.noUiSlider.set([this._inputMin.value, null]);
    }
  }

  resetMax() {
    this._inputMax.value = this.options.price.max;
    this.slider.noUiSlider.set([null, this._inputMax.value]);
  }
}
