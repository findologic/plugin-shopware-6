import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterCategorySelectPlugin extends FilterBasePlugin {
    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-category-select-checkbox',
        countSelector: '.filter-multi-select-count',
        listItemSelector: '.filter-multi-select-list-item',
        icon: {
            selector: '.category-filter-container .category-toggle-icon',
            state: {
                openClass: 'open-icon',
                closedClass: 'closed-icon',
            }
        },
        subCategory: {
            state: {
                openClass: 'show-category-list-item',
                closedClass: 'hide-category-list-item',
            }
        },
        snippets: {
            disabledFilterText: 'Filter not active'
        },
        mainFilterButtonSelector: '.filter-panel-item-toggle',
        filter: []
    });

    init() {
        this.selectedFilterValues = [];
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);
        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        const icons = DomAccess.querySelectorAll(this.el, this.options.icon.selector, false);

        if (icons) {
            Iterator.iterate(icons, (icon) => {
                icon.addEventListener('click', () => {
                    this.onIconClick(icon);
                });
            });
        }

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change', () => {
                this.onChangeCheckBox(checkbox);
                this._onChangeFilter();
            });
        });
    }

    /**
     * @return {Array}
     * @public
     */
    getValues() {
        const selectedCategories = this.getSelectedCategories();
        if (!selectedCategories) {
            return [];
        }

        const selectedFilterValues = [];
        Iterator.iterate(selectedCategories, (category) => {
            selectedFilterValues.push(category.value);
        });

        this.selectedFilterValues = selectedFilterValues;
        this._updateCount();

        const values = [];
        values[this.options.name] = selectedFilterValues;

        return values;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        const selectedCategories = this.getSelectedCategories();
        if (!selectedCategories) {
            return [];
        }

        const labels = [];
        const labelMap = [];
        Iterator.iterate(selectedCategories, (category) => {
            if (category) {
                const categoryName = category.dataset.label;
                const parentCategoryName = category.value.split('_')[0];
                labelMap[parentCategoryName] = {
                    label: categoryName,
                    id: category.id
                }
            }
        });

        Object.keys(labelMap).forEach(label => {
            labels.push(labelMap[label]);
        });

        return labels;
    }

    /**
     * @public
     * @param {Array<String, String|Int|Float|Boolean>} params
     */
    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                stateChanged = true;
                const selectedCategories = params[key].split('|');
                this._selectCategories(selectedCategories);
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
     * @public
     */
    toggleIconState(icon) {
        // Last category of a tree may not have an icon.
        if (typeof icon !== 'undefined' && icon !== null) {
            const classList = icon.classList;
            const isOpen = classList.contains('open-icon');

            if (isOpen) {
                classList.remove(this.options.icon.state.openClass);
                classList.add(this.options.icon.state.closedClass);
            } else {
                classList.remove(this.options.icon.state.closedClass);
                classList.add(this.options.icon.state.openClass);
            }
        }
    }

    /**
     * @param {HTMLObjectElement} checkbox
     * @public
     */
    onChangeCheckBox(checkbox) {
        const icon = checkbox.parentNode.querySelector('.category-toggle-icon');
        const subCategory = icon !== null ? icon.nextElementSibling : null;

        if (!checkbox.checked) {
            const subCategories = checkbox.parentNode.getElementsByClassName('sub-item');
            Iterator.iterate(subCategories, (category) => {
                category.querySelector('.filter-category-select-checkbox').checked = false;
            });

            this.toggleIconState(icon);
            this.toggleSubCategoryVisibility(subCategory);
            return;
        }

        const categoryNames = checkbox.value.split('_');

        if (categoryNames.length > 0) {
            let categoryNameAsId = '';
            Iterator.iterate(categoryNames, (name) => {
                categoryNameAsId += categoryNameAsId === '' ? name : '_' + name;
                document.getElementById(categoryNameAsId).checked = true;
            });
        }

        this.toggleIconState(icon);
        this.toggleSubCategoryVisibility(subCategory);
    }

    /**
     * @param {HTMLObjectElement} icon
     * @public
     */
    onIconClick(icon) {
        const subCategory = icon !== null ? icon.nextElementSibling : null;

        this.toggleIconState(icon);
        this.toggleSubCategoryVisibility(subCategory);
    }

    /**
     * @param {HTMLObjectElement} subCategory
     * @public
     */
    getSiblingsCategories(subCategory) {
        const siblingCategories = [];
        let sibling = '';
        if (subCategory !== null) {
            siblingCategories.push(subCategory);
            do {
                sibling = siblingCategories[siblingCategories.length - 1].nextElementSibling;
                if (sibling) {
                    siblingCategories.push(sibling);
                }
            } while (sibling !== null);
        }

        return siblingCategories;
    }

    /**
     * @param {HTMLObjectElement} subCategory
     * @public
     */
    toggleSubCategoryVisibility(subCategory) {
        const siblingCategories = this.getSiblingsCategories(subCategory);
        if (siblingCategories.length > 0) {
            Iterator.iterate(siblingCategories, (container) => {
                const classList = container.classList;
                const isOpen = classList.contains(this.options.subCategory.state.openClass);

                if (isOpen) {
                    classList.remove(this.options.subCategory.state.openClass);
                    classList.add(this.options.subCategory.state.closedClass);
                } else {
                    classList.remove(this.options.subCategory.state.closedClass);
                    classList.add(this.options.subCategory.state.openClass);
                }
            });
        }
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
     * Selects the given list of categories.
     *
     * @param {String[]} selectedCategories
     * @private
     */
    _selectCategories(selectedCategories) {
        Iterator.iterate(selectedCategories, (selectedCategory) => {
            const checkbox = DomAccess.querySelector(this.el, `[id = "${selectedCategory}"]`, false);

            if (checkbox) {
                this.enableOption(checkbox);
                checkbox.disabled = false;
                checkbox.checked = true;
                this.selectedFilterValues.push(checkbox.value);

                // Toggle icon state and simulate icon clicks to open all subcategories.
                const icon = checkbox.parentElement.querySelector('.category-toggle-icon');
                this.toggleIconState(icon);
                this.onIconClick(checkbox);
            }
        });
    }

    /**
     * @public
     * @return {NodeList|false}
     */
    getSelectedCategories() {
        return DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);
    }

    /**
     * @param {Array} filter
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
        }
        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }

    /**
     * @param {Array} activeItemIds
     * @private
     */
    _disableInactiveFilterOptions(activeItemIds) {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        Iterator.iterate(checkboxes, (checkbox) => {
            const checkboxParentIds = checkbox.id.split('_');
            const checkboxId = checkboxParentIds[checkboxParentIds.length - 1];

            if (!activeItemIds.includes(checkboxId)) {
                this.disableOption(checkbox);
                return;
            }

            this.enableOption(checkbox);
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
