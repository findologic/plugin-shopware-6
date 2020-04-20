import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterCategorySelectPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-category-select-checkbox',
        countSelector: '.filter-multi-select-count',
    });

    init()
    {
        this.selection = [];
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents()
    {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change', this._onChangeFilter.bind(this));
        });
    }

    /**
     * @return {Array}
     * @public
     */
    getValues()
    {
        const checkedCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let selection = [];

        if (checkedCheckboxes) {
            Iterator.iterate(checkedCheckboxes, (checkbox) => {
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
    getLabels()
    {
        const activeCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let labels = [];

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
                labels.push({
                    label: checkbox.dataset.label,
                    id: checkbox.id,
                });
            });
        } else {
            labels = [];
        }

        return labels;
    }

    setValuesFromUrl(params)
    {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                stateChanged = true;
                const ids = params[key].split('_');

                this._disableAllCheckbox()

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
    _onChangeFilter()
    {
        const activeCheckboxes = DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        if (!activeCheckboxes.length) {
            this.resetAll();
        } else {
            this._disableAllCheckbox();
            this._updateParentProperty(activeCheckboxes[0]);
            activeCheckboxes[0].disabled = false;
            activeCheckboxes[0].checked = true;
        }

        this.listing.changeListing();
    }

    /**
     * @param id
     * @public
     */
    reset(id)
    {
        const checkboxEl = DomAccess.querySelector(this.el, `[id = "${id}"]`, false);
        if (checkboxEl) {
            this.resetAll();
        }
    }

    /**
     * @public
     */
    resetAll()
    {
        this.selection.filter = [];

        this._resetCheckboxes();
    }

    /**
     * @private
     */
    _updateCount()
    {
        this.counter.innerText = this.selection.length ? `(${this.selection.length})` : '';
    }

    _disableAllCheckbox()
    {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
            checkbox.disabled = true;
        });
    }

    // Propagate change upwards
    _updateParentProperty(current, prop = 'indeterminate')
    {
        const parent = current.closest('li');

        const parsed = parent.querySelectorAll('.filter-category-select-checkbox:' + prop)

        if (parsed.length === 0) {
            const child = parent.querySelector('.filter-category-select-checkbox');
            if (child) {
                child[prop] = true;
            }
            this._updateParentProperty(parent);
        }
    }

    _setCurrentCategoryAsSelected(ids)
    {
        const selectedCategory = ids.pop();

        // Parent categories of selected category
        ids.forEach(id => {
            const checkboxEl = DomAccess.querySelector(this.el, `[id = "${id}"]`, false);
            if (checkboxEl) {
                this._updateParentProperty(checkboxEl);
            }
        });

        // Selected category
        const checkboxEl = DomAccess.querySelector(this.el, `[id = "${selectedCategory}"]`, false);
        if (checkboxEl) {
            checkboxEl.disabled = false;
            checkboxEl.checked = true;
            checkboxEl.indeterminate = false;
            this.selection.push(checkboxEl.value);
        }
    }

    _resetCheckboxes()
    {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.checked = false;
            checkbox.disabled = false;
            checkbox.indeterminate = false;
        });
    }
}
