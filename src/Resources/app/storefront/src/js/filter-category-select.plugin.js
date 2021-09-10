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
        this.hideUnchecked();
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);
        this._registerEvents();

    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        const arrowIcon = DomAccess.querySelectorAll(this.el,this.options.arrowIcon);
        const current = this; // because some functions need to call inside
                              // another object, for example assigning an event

        if(arrowIcon !== false)
        {
<<<<<<< Updated upstream
                Iterator.iterate(arrowIcon,(arrow)=>
                {
                    arrow.addEventListener('click',function()
                    {
                        current._onArrowClick(arrow)
                    });
                });
=======
            Iterator.iterate(arrowIcon,(arrow)=>
            {
                arrow.addEventListener('click',function()
                {
                    current._onArrowClick(arrow)
                });
            });
>>>>>>> Stashed changes
        }
        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change',function()
            {
                current.showHide(this);
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
        const activeCheckboxes = this.getSelected();
        if(activeCheckboxes !==false) {
            Iterator.iterate(activeCheckboxes, (activeBoxes) => {
                if (activeBoxes) {
                    labels.push({
                        label: activeBoxes.dataset.label,
                        id: activeBoxes.id
                    });
                }
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
                const ids = params[key].split('_')
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

<<<<<<< Updated upstream
     showHide(checkbox)
    {
            let inner_sub_cats = checkbox.parentNode.getElementsByClassName('sub-item');
            let span = checkbox.parentNode.querySelector('#arrow');

            if(checkbox.checked)
            {
                let split_cats = checkbox.value.split('_');

                if(split_cats.length > 0)
                {
                    Iterator.iterate(split_cats,(id) =>
                    {
                        document.getElementById(id).checked = true;
                    })
                }


                this.toggleArrows(span,'down-arrow','up-arrow');
                this.toggleShowHide(inner_sub_cats,'subcats-hide','subcats-show')

            }
            else
            {

                Iterator.iterate(inner_sub_cats,(sub_cat)=>
                {
                    sub_cat.querySelector('.filter-category-select-checkbox').checked = false;

                })
            }
=======
    showHide(checkbox)
    {
        let inner_sub_cats = checkbox.parentNode.getElementsByClassName('sub-item');
        let span = checkbox.parentNode.querySelector('#arrow');

        if(checkbox.checked)
        {
            let split_cats = checkbox.value.split('_');

            if(split_cats.length > 0)
            {
                Iterator.iterate(split_cats,(id) =>
                {
                    document.getElementById(id).checked = true;
                })
            }


            this.toggleArrows(span,'down-arrow','up-arrow');
            this.toggleShowHide(inner_sub_cats,'subcats-hide','subcats-show')

        }
        else
        {

            Iterator.iterate(inner_sub_cats,(sub_cat)=>
            {
                sub_cat.querySelector('.filter-category-select-checkbox').checked = false;

            })
        }
>>>>>>> Stashed changes


    }

    /**
     * public
     */

    toggleArrows(elem,removeClass,addClass)
    {
<<<<<<< Updated upstream
       let span = elem;
=======
        let span = elem;
>>>>>>> Stashed changes
        if(span !== undefined && span !== null)
        {
            span.classList.remove(removeClass);
            span.classList.add(addClass);
        }
    }

    /**
     * public
     */
    toggleByArrows(span)
    {

        let elem = span.parentNode.querySelectorAll('.sub-item')
        let classList = span.classList;
        Object.keys(classList).forEach(key=>{
            if(classList[key].indexOf('up-arrow') > -1)
            {
                this.toggleArrows(span,'up-arrow','down-arrow')
                this.toggleShowHide(elem,'subcats-show','subcats-hide');
            }
            else if(classList[key].indexOf('down-arrow') > -1) {
                this.toggleArrows(span,'down-arrow','up-arrow')
                this.toggleShowHide(elem,'subcats-hide','subcats-show');
            }
        })



    }

    /**
     * public
     */
    _onArrowClick(arrow)
    {
        this.toggleByArrows(arrow)
    }


    /**
     * public
     */

    toggleShowHide(elem,removeClass,showClass)
    {
        if(elem !== undefined && elem !== null) {
            Iterator.iterate(elem, (subcats) => {
                subcats.classList.remove(removeClass);
                subcats.classList.add(showClass)
            })
        }
    }

    /**
     * public
     */

    hideUnchecked()
    {

<<<<<<< Updated upstream
      let checkboxes = DomAccess.querySelectorAll(this.el,this.options.checkboxSelector);
      Iterator.iterate(checkboxes , (checkbox) => {
          let sub_items = checkbox.parentNode.querySelectorAll('.sub-item')
          let span = checkbox.parentNode.getElementsByTagName('div')[0];
          if(checkbox.checked)
          {
              this.toggleArrows(span,'down-arrow','up-arrow')
              this.toggleShowHide(sub_items,'subcats-hide','subcats-show');
          }

      })
=======
        let checkboxes = DomAccess.querySelectorAll(this.el,this.options.checkboxSelector);
        Iterator.iterate(checkboxes , (checkbox) => {
            let sub_items = checkbox.parentNode.querySelectorAll('.sub-item')
            let span = checkbox.parentNode.getElementsByTagName('div')[0];
            if(checkbox.checked)
            {
                this.toggleArrows(span,'down-arrow','up-arrow')
                this.toggleShowHide(sub_items,'subcats-hide','subcats-show');
            }

        })
>>>>>>> Stashed changes

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
