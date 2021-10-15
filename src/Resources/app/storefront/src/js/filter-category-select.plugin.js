import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterCategorySelectPlugin extends FilterBasePlugin {
    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-category-select-checkbox',
        countSelector: '.filter-multi-select-count',
        listItemSelector: '.filter-multi-select-list-item',
        iconsSelector: '.category-filter-container #categoryToggleIcon',
        snippets: {
            disabledFilterText: 'Filter not active'
        },
        mainFilterButtonSelector: '.filter-panel-item-toggle'
    });

    init() {
        this.selectedFilterValues = [];
        this.showActiveCategories();
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);
        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        const icons = DomAccess.querySelectorAll(this.el, this.options.iconsSelector);
        // Because some functions need to call inside another object, for example assigning an event.
        const current = this;
        if (icons.length > 0) {
            Iterator.iterate(icons, (icon) => {
                icon.addEventListener('click', function () {
                    current.toggleCategoryListItemAndIconState(icon);
                });
            });
        }

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change', function () {
                current.onChangeCheckBox(this);
            })
            checkbox.addEventListener('change', this._onChangeFilter.bind(this));
        });
    }

    /**
     * @return {Array}
     * @public
     */
    getValues() {

        const selectedCategories = this.getSelected();
        const selectedFilterValues = [];
        const values = {};
        if (selectedCategories) {
            Iterator.iterate(selectedCategories, (category) => {
                selectedFilterValues.push(category.value);
            });
        }
        this.selectedFilterValues = selectedFilterValues;
        this._updateCount();
        values[this.options.name] = selectedFilterValues;
        return values;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        const labels = [];
        const labelsMapping = [];
        const selectedCategories = this.getSelected();
        if (selectedCategories) {
            Iterator.iterate(selectedCategories, (category) => {
                if (category) {
                    const categoryName = category.dataset.label;
                    const parentCategoryName = category.value.split('_')[0];
                    labelsMapping[parentCategoryName] = {
                        label: categoryName,
                        id: category.id
                    }
                }
            });
            Object.keys(labelsMapping).forEach(label => {
                labels.push(labelsMapping[label]);
            });
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
        this.listing.changeListing();
    }

    /**
     * @param {HTMLObjectElement} icon
     * @param {string} removeClass
     * @param {string} addClass
     * @public
     */
    toggleCategoryListIconState(icon, removeClass, addClass) {
        if (typeof icon !== undefined && icon !== null) {
            icon.classList.remove(removeClass);
            icon.classList.add(addClass);
        }
    }

    /**
     * @param {HTMLObjectElement} checkbox
     * @public
     */
    onChangeCheckBox(checkbox) {
        const subCategories = checkbox.parentNode.getElementsByClassName('sub-item');
        const icon = checkbox.parentNode.querySelector('#categoryToggleIcon');
        const subCategory = icon !== null ? icon.nextElementSibling : null;

        if (!checkbox.checked) {
            Iterator.iterate(subCategories, (category) => {
                category.querySelector('.filter-category-select-checkbox').checked = false;
            });
            return;
        }

        const categoryNames = checkbox.value.split('_');

        if (categoryNames.length > 0) {
            let categoryNameAsId = '';
            Iterator.iterate(categoryNames, (name) => {
                categoryNameAsId += categoryNameAsId === '' ? name : "_" + name;
                document.getElementById(categoryNameAsId).checked = true;
            });
        }

        this.toggleCategoryListIconState(icon, 'down-icon', 'up-icon');
        this.toggleSubcategoryDisplay(subCategory, 'hide-category-list-item', 'show-category-list-item');
    }

    /**
     * @param {HTMLObjectElement} icon
     * @public
     */
    toggleCategoryListItemAndIconState(icon) {
        const subCategory = icon !== null ? icon.nextElementSibling : null;
        const classList = icon.getAttribute('class');
        const isDropdownOpen = classList.indexOf('up-icon') > -1;

        if (isDropdownOpen) {
            this.toggleCategoryListIconState(icon, 'up-icon', 'down-icon');
            this.toggleSubcategoryDisplay(subCategory, 'show-category-list-item', 'hide-category-list-item');
            return;
        }
        this.toggleCategoryListIconState(icon, 'down-icon', 'up-icon');
        this.toggleSubcategoryDisplay(subCategory, 'hide-category-list-item', 'show-category-list-item');
    }

    /**
     * @param {HTMLObjectElement} subCategory
     * @public
     */
    siblingsCategories(subCategory) {
        const siblingCategories = [];
        let sibling = '';
        if (subCategory !== null) {
            siblingCategories.push(subCategory);
            do {
                sibling = siblingCategories[siblingCategories.length - 1].nextElementSibling;
                siblingCategories.push(sibling !== null ? sibling : '');
            } while (sibling !== null);
        }

        return siblingCategories;
    }

    /**
     * @param {HTMLObjectElement} subCategory
     * @param {string} hideClass
     * @param {string} showClass
     * @public
     */
    toggleSubcategoryDisplay(subCategory, hideClass, showClass) {
        const siblingCategories = this.siblingsCategories(subCategory);
        if (typeof (siblingCategories) === "object") {
            Iterator.iterate(siblingCategories, (container) => {
                if (container !== null && container !== '') {
                    container.classList.remove(hideClass);
                    container.classList.add(showClass);
                }
            });
        }
    }

    /**
     * @public
     */
    showActiveCategories() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        Iterator.iterate(checkboxes, (checkbox) => {

            const icon = checkbox.parentNode.querySelector('#categoryToggleIcon');
            const subCategory = icon !== null ? icon.nextElementSibling : null;
            if (checkbox.checked) {
                this.toggleCategoryListIconState(icon, 'down-icon', 'up-icon');
                this.toggleSubcategoryDisplay(subCategory, 'hide-category-list-item', 'show-category-list-item');
            }
        });
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
        this.selectedFilterValues.filter = [];
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
        const checkboxEl = DomAccess.querySelector(this.el, `[id = "${selectedCategory}"]`, false);
        if (checkboxEl) {
            this.enableOption(checkboxEl);
            checkboxEl.disabled = false;
            checkboxEl.checked = true;
            this.selectedFilterValues.push(checkboxEl.value);
        }
    }

    /**
     * @public
     */
    getSelected() {
        return DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);
    }

    /**
     *
     * @param {array} filter
     */
    refreshDisabledState(filter) {
        const activeItems = [];
        const properties = filter[this.options.name];
        const entities = properties.entities;
        if (entities.length === 0) {
            this._disableAll();
            return;
        }
        const property = entities.find(entity => entity.translated.name === this.options.name);
        if (property) {
            activeItems.push(...property.options);
            return;
        }
        this._disableAll();
        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }

    /**
     * @param {array} activeItemIds
     * @private
     */
    _disableInactiveFilterOptions(activeItemIds) {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        Iterator.iterate(checkboxes, (checkbox) => {
            if (checkbox.checked) {
                this.enableOption(checkbox);
                return;
            }
            if (activeItemIds.includes(checkbox.id)) {
                this.enableOption(checkbox);
                return
            }
            this.disableOption(checkbox);

        });
    }

    /**
     * @param {HTMLObjectElement} input
     * @public
     */
    disableOption(input) {
        const listItem = input.closest('.custom-checkbox');
        listItem.classList.add('fl-disabled');
        listItem.setAttribute('title', this.options.snippets.disabledFilterText);
        input.disabled = true;
    }

    /**
     * @param {HTMLObjectElement} input
     * @public
     */
    enableOption(input) {
        const listItem = input.closest('.custom-checkbox');
        listItem.removeAttribute('title');
        listItem.classList.remove('fl-disabled');
        input.disabled = false;
    }

    /**
     * @public
     */
    enableAllOptions() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        Iterator.iterate(checkboxes, (checkbox) => {
            this.enableOption(checkbox);
        });
    }

    /**
     * @public
     */
    disableFilter() {
        const mainFilterButton = DomAccess.querySelector(this.el, this.options.mainFilterButtonSelector);
        mainFilterButton.classList.add('fl-disabled');
        mainFilterButton.setAttribute('disabled', 'disabled');
        mainFilterButton.setAttribute('title', this.options.snippets.disabledFilterText);
    }

    /**
     * @public
     */
    enableFilter() {
        const mainFilterButton = DomAccess.querySelector(this.el, this.options.mainFilterButtonSelector);
        mainFilterButton.classList.remove('fl-disabled');
        mainFilterButton.removeAttribute('disabled');
        mainFilterButton.removeAttribute('title');
    }
}
