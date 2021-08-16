import deepmerge from 'deepmerge';
import DomAccess from 'src/helper/dom-access.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';

export default class FilterSliderRange extends FilterBasePlugin {

  static options = deepmerge(FilterBasePlugin.options, {
    inputMinSelector: '.min-input',
    inputMaxSelector: '.max-input',
    inputInvalidCLass: 'is-invalid',
    inputTimeout: 500,
    minKey: 'min-price',
    maxKey: 'max-price',
    price: {
      min: 0,
      max: 1,
      step: 0.1
    },
    errorContainerClass: 'filter-range-error',
    containerSelector: '.filter-range-container',
    sliderContainer: '.fl--range-slider',
    snippets: {
      filterRangeActiveMinLabel: '',
      filterRangeActiveMaxLabel: '',
      filterRangeErrorMessage: ''
    }
  });

  init() {
    this.resetState();

    this._container = DomAccess.querySelector(this.el, this.options.containerSelector);
    this._inputMin = DomAccess.querySelector(this.el, this.options.inputMinSelector);
    this._inputMax = DomAccess.querySelector(this.el, this.options.inputMaxSelector);
    this._timeout = null;
    this._hasError = false;

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
        'max': this.getMax()
      },
    });

    this._registerEvents();
  }

  /**
   * Reset state in case the filter was already loaded once e.g. when opening the off-canvas filter panel
   * multiple times.
   *
   * @private
   */
  resetState() {
    DomAccess.querySelector(this.el, this.options.sliderContainer).innerHTML = '';
  }

  /**
   * @private
   */
  _registerEvents() {
    // Register slider events
    this.slider.noUiSlider.on('update', this.onUpdateValues.bind(this));
    this.slider.noUiSlider.on('end', this._onChangeInput.bind(this));

    this._inputMin.addEventListener('blur', this._onChangeMin.bind(this));
    this._inputMax.addEventListener('blur', this._onChangeMax.bind(this));

    this._inputMin.addEventListener('keyup', this._onInput.bind(this));
    this._inputMax.addEventListener('keyup', this._onInput.bind(this));
  }

  /**
   * @returns {float}
   */
  getMax() {
    return this.options.price.max === this.options.price.min ? this.options.price.min + 1 : this.options.price.max;
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

  /**
   * @param {KeyboardEvent} e
   * @private
   */
  _onInput(e) {
    if (e.keyCode === 13) {
      e.target.blur();
    }
  }

  /**
   * @private
   */
  _onChangeInput() {
    clearTimeout(this._timeout);

    this._timeout = setTimeout(() => {
      if (this._isInputInvalid()) {
        this._setError();
      } else {
        this._removeError();
      }
      this.listing.changeListing();
    }, this.options.inputTimeout);
  }

  /**
   * @return {boolean}
   * @private
   */
  _isInputInvalid() {
    return parseInt(this._inputMin.value) > parseInt(this._inputMax.value);
  }

  /**
   * @return {string}
   * @private
   */
  _getErrorMessageTemplate() {
    return `<div class="${this.options.errorContainerClass}">${this.options.snippets.filterRangeErrorMessage}</div>`;
  }

  /**
   * @private
   */
  _setError() {
    if (this._hasError) {
      return;
    }

    this._inputMin.classList.add(this.options.inputInvalidCLass);
    this._inputMax.classList.add(this.options.inputInvalidCLass);
    this._container.insertAdjacentHTML('afterend', this._getErrorMessageTemplate());
    this._hasError = true;
  }

  /**
   * @private
   */
  _removeError() {
    this._inputMin.classList.remove(this.options.inputInvalidCLass);
    this._inputMax.classList.remove(this.options.inputInvalidCLass);

    const error = DomAccess.querySelector(this.el, `.${this.options.errorContainerClass}`, false);

    if (error) {
      error.remove();
    }

    this._hasError = false;
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
   * @param {Array} params
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

  /**
   * @param {Array} values
   */
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
   * @param {String} id
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
    } else {
      this.setMinKnobValue();
    }
  }

  validateMaxInput() {
    if (!this._inputMax.value || this._inputMax.value > this.options.price.max || this._inputMax.value < this.options.price.min) {
      this.resetMax();
    } else {
      this.setMaxKnobValue();
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

  /**
   * @private
   */
  _onChangeMin() {
    this.setMinKnobValue();
    this._onChangeInput();
  }

  /**
   * @private
   */
  _onChangeMax() {
    this.setMaxKnobValue();
    this._onChangeInput();
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
    if (this.slider) {
      this.slider.noUiSlider.set([this._inputMin.value, null]);
    }
  }

  setMaxKnobValue() {
    if (this.slider) {
      this.slider.noUiSlider.set([null, this._inputMax.value]);
    }
  }

  refreshDisabledState(filter) {
    const properties = filter[this.options.name];
    const entities = properties.entities;
    if(entities.length > 0) {
      const options = entities[0].options;
      if(options.length >= 4) {
        this._inputMin.value = parseFloat((options[1].id).split('-')[0]);
        this._inputMax.value = parseFloat((options[3].id).split('-')[0]);
        this.setMinKnobValue();
        this.setMaxKnobValue();
      }
    }
  }
}
