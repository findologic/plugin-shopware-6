import FilterRangePlugin from 'src/plugin/listing/filter-range.plugin';
import '../../node_modules/nouislider/distribute/nouislider.css';
import deepmerge from 'deepmerge';
import DomAccess from 'src/helper/dom-access.helper';

const noUiSlider = require('@nouislider');

export default class FlFilterRangePlugin extends FilterRangePlugin {

  static options = deepmerge(FilterRangePlugin.options, {
    sliderContainer: '.fl--range-slider',
  });

  init() {
    super.init();
    this.slider = document.createElement('div');
    this._sliderContainer = DomAccess.querySelector(this.el, this.options.sliderContainer);
    this._sliderContainer.prepend(this.slider);

    let start = this._inputMin.value.length ? this._inputMin.value : this.options.price.min;
    let end = this._inputMax.value.length ? this._inputMax.value : this.options.price.max;

    noUiSlider.create(this.slider, {
      start: [start, end],
      connect: true,
      step: this.options.price.step,
      range: {
        'min': this.options.price.min,
        'max': this.options.price.max,
      },
    });

    // Register slider events
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

    if (this.hasMinValueSet()) {
      values[this.options.minKey] = this._inputMin.value;
    }

    if (this.hasMaxValueSet()) {
      values[this.options.maxKey] = this._inputMax.value;
    }

    return values;
  }

  _onChangeInput() {
    if (this.hasMinValueSet() || this.hasMaxValueSet()) {
      super._onChangeInput();
    }
  }

  /**
   * @private
   */
  _registerEvents() {
    this._inputMin.addEventListener('blur', this._onChangeMin.bind(this));
    this._inputMax.addEventListener('blur', this._onChangeMax.bind(this));
  }

  /**
   * @return {Array}
   * @public
   */
  getLabels() {
    let labels = [];

    if (this._inputMin.value.length || this._inputMax.value.length) {
      if (this.hasMinValueSet()) {
        labels.push({
          label: `${this.options.snippets.filterRangeActiveMinLabel} ${this._inputMin.value} ${this.options.currencySymbol}`,
          id: this.options.minKey,
        });
      }

      if (this.hasMaxValueSet()) {
        labels.push({
          label: `${this.options.snippets.filterRangeActiveMaxLabel} ${this._inputMax.value} ${this.options.currencySymbol}`,
          id: this.options.maxKey,
        });
      }
    } else {
      labels = [];
    }

    return labels;
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
    if (!this._inputMin.value || this._inputMin.value < this.options.price.min || this._inputMin.value > this.options.price.max) {
      this.resetMin();
    }
  }

  validateMaxInput() {
    if (!this._inputMax.value || this._inputMax.value > this.options.price.max || this._inputMax.value < this.options.price.min) {
      this.resetMax();
    }
  }

  resetMin() {
    this._inputMin.value = this.options.price.min;
    this.setMinKnobValue();
  }

  resetMax() {
    this._inputMax.value = this.options.price.max;
    this.setMaxKnobValue();
  }

  _onChangeMin() {
    this.setMinKnobValue(this.value);
  }

  _onChangeMax() {
    this.setMaxKnobValue(this.value);
  }

  hasMinValueSet() {
    this.validateMinInput();
    return this._inputMin.value.length && parseFloat(this._inputMin.value) !== this.options.price.min;
  }

  hasMaxValueSet() {
    this.validateMaxInput();
    return this._inputMax.value.length && parseFloat(this._inputMax.value) !== this.options.price.max;
  }

  setMinKnobValue() {
    if (this.slider && this.hasMinValueSet()) {
      this.slider.noUiSlider.set([this._inputMin.value, null]);
    }
  }

  setMaxKnobValue() {
    if (this.slider && this.hasMaxValueSet()) {
      this.slider.noUiSlider.set([null, this._inputMax.value]);
    }
  }
}
