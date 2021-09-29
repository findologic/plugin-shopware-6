import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterCategorySelectPlugin extends FilterBasePlugin {
    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-category-select-checkbox',
        countSelector: '.filter-multi-select-count',
        listItemSelector: '.filter-multi-select-list-item',
        arrowIcon : '.category_div_adjust  #arrow',
        snippets: {
            disabledFilterText: 'Filter not active'
        },
        mainFilterButtonSelector: '.filter-panel-item-toggle'
    });
    
    init() {
        this.selection = [];
        this.showActiveCategory();
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);
        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        const arrowIcon = DomAccess.querySelectorAll(this.el,this.options.arrowIcon);
        
        // because some functions need to call inside another object, for example assigning an event.
        const current = this;
        if(arrowIcon !== false) {
            Iterator.iterate(arrowIcon,(arrow)=> {
                arrow.addEventListener('click',function() {
                    current._onArrowClick(arrow);
                });
            });
        }

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change',function() {
                current.subCategoryDisplay(this);
            })
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

        if(activeCheckboxes !== false) {
            Iterator.iterate(activeCheckboxes, (activeBoxes) => {
                selection.push(activeBoxes.value);
            });
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
        let catArray =[];
        let lastLabel = "";
        const activeCheckboxes = this.getSelected();
        if(activeCheckboxes !== false) {
            Iterator.iterate(activeCheckboxes, (activeBoxes) => {
                if (activeBoxes){
                    let boxLabel = activeBoxes.dataset.label;
                    let indexCat = activeBoxes.value.split('_')[0];
                        catArray[indexCat] = {label:boxLabel,id:activeBoxes.id}
                }
            });
            Object.keys(catArray).forEach(cats=>{
                labels.push(catArray[cats]);
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
     * @public
     */
    toggleCategoryArrows(elem,removeClass,addClass) {
        let span = elem;
        if(span !== undefined && span !== null) {
            span.classList.remove(removeClass);
            span.classList.add(addClass);
        }
    }


    /**
     * @public
     */
    subCategoryDisplay(checkbox) {
        let inner_sub_cats = checkbox.parentNode.getElementsByClassName('sub-item');
        let span = checkbox.parentNode.querySelector('#arrow');
        let elem = span!==null ? span.nextElementSibling : null;
        let elem_array = this.siblingsCategories(elem);
        if(checkbox.checked) {
            let split_cats = checkbox.value.split('_');
            if(split_cats.length > 0) {
                Iterator.iterate(split_cats,(id) => {
                    document.getElementById(id).checked = true;
                })
            }
            this.toggleCategoryArrows(span,'down-arrow','up-arrow');
            this.toggleSubcategoryDisplay(elem_array,'subcats-hide','subcats-show');
        } else {
            Iterator.iterate(inner_sub_cats, (sub_cat)=> {
                sub_cat.querySelector('.filter-category-select-checkbox').checked = false;
            });
        }
    }

    /**
     * @public
     */
    toggleSubcategoryDisplayByArrows(span) {
        let elem = span!==null ? span.nextElementSibling : null;
        let elem_array = this.siblingsCategories(elem);

        let classList = span.getAttribute('class');
        let check = classList.indexOf('up-arrow') > -1;

        if(check) {
            this.toggleCategoryArrows(span,'up-arrow','down-arrow');
            this.toggleSubcategoryDisplay(elem_array,'subcats-show','subcats-hide');
        } else {
            this.toggleCategoryArrows(span,'down-arrow','up-arrow');
            this.toggleSubcategoryDisplay(elem_array,'subcats-hide','subcats-show');
        }
    }

    /**
     * @public
     */
    siblingsCategories(elem) {
        let elem_array = [];
        if(elem!== null){
            elem_array = [elem];
            do {
                var sibs = elem_array[elem_array.length -1].nextElementSibling;
                elem_array.push(sibs!==null ? sibs : "");
            }
            while( sibs !== null);
        }
        return elem_array;
    }

    /**
     * @public
     */
    _onArrowClick(arrow) {
        this.toggleSubcategoryDisplayByArrows(arrow);
    }

    /**
     * @public
     */
    toggleSubcategoryDisplay(elem,removeClass,showClass) {
        if(elem!== undefined && elem!== null) {
            Iterator.iterate(elem, (subcats) => {
                if(subcats!== null && subcats!== "") {
                    if(subcats.classList === undefined) {
                        console.log(subcats);
                    }
                    subcats.classList.remove(removeClass);
                    subcats.classList.add(showClass);
                }
            });
        }
    }

    /**
     * @public
     */
    showActiveCategory() {
        let checkboxes = DomAccess.querySelectorAll(this.el,this.options.checkboxSelector);
        Iterator.iterate(checkboxes , (checkbox) => {
            let sub_items = checkbox.parentNode.querySelectorAll('.sub-item');
            let span = checkbox.parentNode.getElementsByTagName('div')[0];
            if(checkbox.checked) {
                this.toggleCategoryArrows(span,'down-arrow','up-arrow');
                this.toggleSubcategoryDisplay(sub_items,'subcats-hide','subcats-show');
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
        const checkboxEl = DomAccess.querySelector(this.el, `[id = "${selectedCategory}"]`, false);
        if (checkboxEl) {
            this.enableOption(checkboxEl);
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
            if (checkbox.checked === true) {
                this.enableOption(checkbox);
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
        let listItem = input.closest('.custom-checkbox');
        listItem.classList.add('fl-disabled');
        listItem.setAttribute('title', this.options.snippets.disabledFilterText);
        input.disabled = true;
    }

    /**
     * @public
     */
    enableOption(input) {
        let listItem = input.closest('.custom-checkbox');
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
