import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterCategorySelectPlugin extends FilterBasePlugin {
    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-category-select-checkbox',
        countSelector: '.filter-multi-select-count',
        listItemSelector: '.filter-multi-select-list-item',
        snippets: {
            disabledFilterText: 'Filter not active'
        },
        mainFilterButtonSelector: '.filter-panel-item-toggle'
    });

    init() {
        this.selection = [];
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);

        setTimeout(() => {
            this.listing.registerFilter(this);
        }, 500);

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change', this._onChangeFilter.bind(this));
        });
    }

    /**
     * @return {Array}
     * @public
     */
    getValues() {
        const activeCheckboxes = this.getSelected();

        let selection = [];

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
                selection.push(checkbox.value);
            });
        } else {
            selection = [];
        }

        this.selection = selection;
        this._updateCount();

        const values = {};
        values[this.options.name] = selection;

        return values;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        let labels = [];

        const activeCheckboxes = this.getSelected();

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
                labels.push({
                    label: checkbox.dataset.label,
                    id: checkbox.id
                });
            });
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @public
     * @param params
     */
    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                stateChanged = true;
                const ids = params[key].split('_');

                this._disableAll();
                this._setCurrentCategoryAsSelected(ids);
            }
        });

        if (!stateChanged) {
            this.resetAll();
        }

        this._updateCount();

        return stateChanged;
    }

    /**
     * @private
     */
    _onChangeFilter() {
        const activeCheckboxes = this.getSelected();

        if (!activeCheckboxes.length) {
            this.resetAll();
        } else {
            this._disableAll();
            activeCheckboxes[0].disabled = false;
            activeCheckboxes[0].checked = true;
        }

        this.listing.changeListing();
    }

    /**
     * @public
     */
    reset() {
        this.resetAll();
    }

    /**
     * @public
     */
    resetAll() {
        this.selection.filter = [];

        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.checked = false;
            checkbox.disabled = false;
            checkbox.indeterminate = false;
        });
    }

    /**
     * @private
     */
    _updateCount() {
        this.counter.innerText = '';
    }

    /**
     * @private
     */
    _disableAll() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
            checkbox.disabled = true;
        });
    }

    /**
     * @private
     */
    _enableAll() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
            checkbox.disabled = false;
        });
    }

    /**
     * @param ids
     * @private
     */
    _setCurrentCategoryAsSelected(ids) {
        const selectedCategory = ids.pop();

        // Selected category
        const checkboxEl = DomAccess.querySelector(this.el, `[id = "${selectedCategory}"]`, false);
        if (checkboxEl) {
            checkboxEl.disabled = false;
            checkboxEl.checked = true;
            this.selection.push(checkboxEl.value);
        }
    }

    /**
     * @public
     */
    getSelected() {
        return DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);
    }

    refreshDisabledState(filter) {
        const activeItems = [];
        const properties = filter[this.options.name];
        const entities = properties.entities;

        if (entities.length === 0) {
            this._disableAll();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.name);
        console.log({property: property});

        if (property) {
            activeItems.push(...property.options);
        } else {
            this._disableAll();
            return;
        }

        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }



    /**
     * @private
     */
    _disableInactiveFilterOptions(activeItemIds) {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        Iterator.iterate(checkboxes, (checkbox) => {
            console.log({active: activeItemIds});
            console.log({checked: checkbox.id});
            if (checkbox.checked === true) {
                return;
            }

            if (activeItemIds.includes(checkbox.id)) {
                this.enableOption(checkbox);
            } else {
                this.disableOption(checkbox);
            }
        });
    }

    /**
     * @public
     */
    disableOption(input){
        input.disabled = true;
    }

    /**
     * @public
     */
    enableOption(input) {
        input.disabled = false;
    }
}
