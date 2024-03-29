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
        lowerBound: 0,
        mainFilterButtonSelector: '.filter-panel-item-toggle',
        range: {
            min: 0,
            max: 1,
            step: 0.1,
        },
        unit: '',
        errorContainerClass: 'filter-range-error',
        containerSelector: '.filter-range-container',
        sliderContainer: '.fl--range-slider',
        snippets: {
            filterRangeActiveMinLabel: '',
            filterRangeActiveMaxLabel: '',
            filterRangeErrorMessage: '',
            disabledFilterText: 'Filter not active',
        },
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

        const start = this._inputMin.value.length ? this._inputMin.value : this.options.range.min;
        const end = this._inputMax.value.length ? this._inputMax.value : this.options.range.max;
        const min = this.options.range.min;
        const max = this.getMax();

        const startPrecision = this.getPrecision(min);
        const endPrecision = this.getPrecision(max);

        noUiSlider.create(this.slider, {
            start: [start, end],
            connect: true,
            step: this.options.range.step,
            range: { min, max },
            format: {
                to: (v) => parseFloat(v).toFixed(startPrecision),
                from: (v) => parseFloat(v).toFixed(endPrecision),
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
     * @param {number} number
     */
    getPrecision(number) {
        const numberString = number.toString();
        const precision = numberString.indexOf('.') > -1 ? numberString.split('.')[1].length : 2;

        // Show at least 2 decimal places
        return Math.max(2, precision);
    }

    /**
     * @returns {number}
     */
    getMax() {
        return this.options.range.max === this.options.range.min ? this.options.range.min + 1 : this.options.range.max;
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
                    label: `${this.options.snippets.filterRangeActiveMinLabel} ${this._inputMin.value} ${this.options.unit}`,
                    id: this.options.minKey,
                });
            }

            if (this.hasMaxValueSet()) {
                labels.push({
                    label: `${this.options.snippets.filterRangeActiveMaxLabel} ${this._inputMax.value} ${this.options.unit}`,
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
        if (values[0] < this.options.range.min) {
            values[0] = this.options.range.min;
        }
        if (values[1] > this.options.range.max) {
            values[1] = this.options.range.max;
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
        if (!this._inputMin.value || this._inputMin.value < this.options.range.min || this._inputMin.value > this.options.range.max) {
            this.resetMin();
        } else {
            this.setMinKnobValue();
        }
    }

    validateMaxInput() {
        if (!this._inputMax.value || this._inputMax.value > this.options.range.max || this._inputMax.value < this.options.range.min) {
            this.resetMax();
        } else {
            this.setMaxKnobValue();
        }
    }

    resetMin() {
        this._inputMin.value = this.options.range.min;
        this.setMinKnobValue();
    }

    resetMax() {
        this._inputMax.value = this.options.range.max;
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
        return this._inputMin.value.length && parseFloat(this._inputMin.value) > this.options.range.min;
    }

    hasMaxValueSet() {
        this.validateMaxInput();
        return this._inputMax.value.length && parseFloat(this._inputMax.value) < this.options.range.max;
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

    setBothKnobValues() {
        if (this.slider) {
            this.slider.noUiSlider.set([this._inputMin.value, this._inputMax.value]);
        }
    }

    refreshDisabledState(filter) {
        const properties = filter[this.options.name];
        const entities = properties.entities;

        if (!entities || !entities.length) {
            this.disableFilter();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.propertyName);
        if (!property) {
            this.disableFilter();
            return;
        }

        const totalRangePrices = property.options[0].totalRange;
        const currentSelectedPrices = this.getValues();

        if (totalRangePrices.min === totalRangePrices.max) {
            this.disableFilter();
            return;
        }

        if (this.options.range.min !== totalRangePrices.min || this.options.range.max !== totalRangePrices.max) {
            this.updateMinAndMaxValues(totalRangePrices.min, totalRangePrices.max);
        } else {
            this.enableFilter();
            return;
        }

        this.updateSelectedRange(currentSelectedPrices, totalRangePrices);

        this.enableFilter();
    }

    updateMinAndMaxValues(minPrice, maxPrice) {
        this.options.range.min = minPrice;
        this.options.range.max = maxPrice;

        this.slider.noUiSlider.updateOptions({
            range: {
                'min': minPrice,
                'max': maxPrice,
            },
        });

        this.updateInputsAndSliderValues(minPrice, maxPrice);
    }

    updateInputsAndSliderValues(minPrice, maxPrice) {
        if (minPrice !== null) {
            this._inputMin.value = minPrice;
        }

        if (maxPrice !== null) {
            this._inputMax.value = maxPrice;
        }

        this.setBothKnobValues();
    }

    /**
     * @param {Array} currentSelectedPrices
     * @param {Object} totalRangePrices
     */
    updateSelectedRange(currentSelectedPrices, totalRangePrices) {
        const currentSelectedPriceMin = currentSelectedPrices[this.options.minKey];
        const currentSelectedPriceMax = currentSelectedPrices[this.options.maxKey];

        const updateMin = currentSelectedPriceMin && currentSelectedPriceMin >= totalRangePrices.min;
        const updateMax = currentSelectedPriceMax && currentSelectedPriceMax <= totalRangePrices.max;

        const newSelectedMin = updateMin ? currentSelectedPriceMin : null;
        const newSelectedMax = updateMax ? currentSelectedPriceMax : null;

        this.updateInputsAndSliderValues(newSelectedMin, newSelectedMax);
    }

    disableFilter() {
        const mainFilterButton = DomAccess.querySelector(this.el, this.options.mainFilterButtonSelector);
        mainFilterButton.classList.add('fl-disabled');
        mainFilterButton.setAttribute('disabled', 'disabled');
        mainFilterButton.setAttribute('title', this.options.snippets.disabledFilterText);
    }

    enableFilter() {
        const mainFilterButton = DomAccess.querySelector(this.el, this.options.mainFilterButtonSelector);
        mainFilterButton.classList.remove('fl-disabled');
        mainFilterButton.removeAttribute('disabled');
        mainFilterButton.removeAttribute('title');
    }
}
