/**
 * @jest-environment jsdom
 */

import FilterCategorySelectPlugin from '../src/js/filter-category-select.plugin';
import FilterCategorySelectElement from './filter-category-select-helper';
import Iterator from 'src/helper/iterator.helper';
import ListingPlugin
    from '../../../../../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/plugin/listing/listing.plugin';

describe('filter-category-select.plugin.js', () => {
    let filterCategorySelectPlugin;
    let filterCategorySelectElement;
    let $ = require('jquery');
    beforeEach(() => {
        window.csrf = {
      enabled: false
    };

    window.router = [];

    const mockElement = document.createElement('div');

    const cmsElementProductListingWrapper = document.createElement('div');
    cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

    const mockElementSpan = document.createElement('span');
    mockElementSpan.classList.add('filter-multi-select-count');

    const mockElementButton = document.createElement('button');
    mockElementButton.classList.add('filter-panel-item-toggle');
    filterCategorySelectElement = new FilterCategorySelectElement();

    mockElement.appendChild(cmsElementProductListingWrapper);
    mockElement.appendChild(mockElementButton);
    mockElement.appendChild(mockElementSpan);
    mockElement.appendChild(filterCategorySelectElement.createCategoryStructure());

    document.body.appendChild(mockElement);

    window.PluginManager = {
      getPluginInstancesFromElement: () => {
        return new Map();
      },
      getPlugin: () => {
        return {
          get: () => []
        };
      },
      getPluginInstanceFromElement: () => {
        return new ListingPlugin(mockElement);
      },
    };

     filterCategorySelectPlugin = new FilterCategorySelectPlugin(mockElement);
  });

  afterEach(() => {
    filterCategorySelectPlugin = null;
  });

  test('filter category select plugin exists', () => {
    expect(typeof filterCategorySelectPlugin).toBe('object');
  });

  test('On initialization only main parent categories should be visible, all child categories must be hidden', () => {

      let isHideClassContain = false;
      let isShowClassContain = false;
      let categories = $('.category-filter-container');
      let subCategories = $('.sub-item');

      $(categories).each(function (index){
          isShowClassContain = categories[index].classList.contains('category-filter-container');
      });

      $(subCategories).each((index)=>{
          isHideClassContain = subCategories[index].classList.contains('hide-category-list-item');
      });

      expect(isShowClassContain).toBe(true);
      expect(isHideClassContain).toBe(true);

  });

  test('On select Men Category: sub-categories visible on their first level',() => {
      let menCategoryCheckbox = $('input')[0];
      let isUrlUpdated;
      const expectedClass = 'show-category-list-item'
          $(menCategoryCheckbox).click();

      let menSubCategory = $(menCategoryCheckbox).parent()[0].querySelectorAll('.sub-item')
      isUrlUpdated = window.location.search.indexOf('Men') > -1

      let menSiblingCategories = filterCategorySelectPlugin.getSiblingsCategories(menSubCategory[0]);
      let allSiblingsVisible;
      for(let i = 0; i < menSiblingCategories.length; i++){
          if(menSiblingCategories[i].classList.contains(expectedClass)){
              allSiblingsVisible = true;
          }else{
              allSiblingsVisible = false;
              break;
          }
      }

      let isSubHatsCategories = menSubCategory[0].querySelector('.sub-item');
      let isSubHatsCategoriesVisible = isSubHatsCategories.classList.contains('hide-category-list-item');

      expect(menCategoryCheckbox.checked).toBe(true);
      expect(allSiblingsVisible).toBe(true);
      expect(isSubHatsCategoriesVisible).toBe(true)
      expect(isUrlUpdated).toBe(true)
  });

    test('All Parent Categories has icon', () => {
        let categoryCheckboxes = '.filter-category-select-checkbox';
        $(categoryCheckboxes).each(function (index) {
            let categoryParent = $(categoryCheckboxes)[index].parentNode;
            let isSubCategoryExist = categoryParent.querySelector('sub-item');
            let isToggleIconExist = categoryParent.querySelector('category-toggle-icon');
            expect(isSubCategoryExist).toBe(isToggleIconExist);
        });
    });

    test('Selecting Newcomer, updates the url accordingly', () => {
        $('#Newcomers').click();
        let isUrlUpdated = window.location.search.indexOf('Newcomers') > -1;
        expect(isUrlUpdated).toBe(true);
    });

    test('On click women Category Icon: sub-categories visible, no update in url, checkbox not selected', () => {
        let isShowClassExist;
        let womenCategoryToggleIcon = $('.category-toggle-icon')[2];
        $(womenCategoryToggleIcon).click();

        let womenSubCategory = $(womenCategoryToggleIcon).parent()[0].querySelectorAll('.sub-item')[0]
        let womenSubSiblings = filterCategorySelectPlugin.getSiblingsCategories(womenSubCategory);
        womenSubSiblings.push(womenSubCategory);
        for (let i = 0; i < womenSubSiblings.length; i++) {
            isShowClassExist = womenSubSiblings[i].classList.contains('show-category-list-item');
            if (!isShowClassExist) {
                break;
            }
        }

        let isUrlUpdated = window.location.search.indexOf('Women') > -1
        let isCategorySelected = document.getElementById('Women').checked;
        let areMoreDeepVisible = womenSubSiblings[0].querySelectorAll('show-category-list-item').length;

        expect(isShowClassExist).toBe(true);
        expect(isUrlUpdated).not.toBe(true);
        expect(isCategorySelected).not.toBe(true)
        expect(areMoreDeepVisible).not.toBe(true);

    });

    test('Women and hats categories to be selected', () => {
        $('#Women_Hats').click();
        let isCategorySelected = document.getElementById('Women').checked;
        let womenHat = document.getElementById('Women_Hats').checked;
        let isWomenAddedToUrl = window.location.search.indexOf('Women') > -1;
        let isWomenHatAddedToUrl = window.location.search.indexOf('Women_Hats') > -1;
        expect(isCategorySelected).toBe(true);
        expect(womenHat).toBe(true);
        expect(isWomenAddedToUrl).toBe(true);
        expect(isWomenHatAddedToUrl).toBe(true);
    });

    test('Seleting Men category, click on an icon to hide sub categories', () => {

        let menSub = $('#Men').parent()[0].querySelector('.sub-item')
        const subCategory = menSub
        const icon = $('.category-toggle-icon')[0];
        icon.dispatchEvent(new Event('click'))
        let isSubHide = subCategory.classList.contains('hide-category-list-item');
        expect(isSubHide).toBe(true);
    });

    test('Ensure that deselecting an already selected category, will automatically hide all subcategories', () => {
        const icon = $('.category-toggle-icon')
        icon[0].dispatchEvent(new Event('click')); // To show Subcategories, so can be hidden on deslecting parent
        let subCategory = $('#Men').parent()[0].querySelector('.sub-item');
        let isSubHide = subCategory.classList.contains('show-category-list-item');
        expect(isSubHide).toBe(true);
        $('#Men').click();
        isSubHide = subCategory.classList.contains('hide-category-list-item');
        expect(isSubHide).toBe(true);
    });

    test('Disable Inactive Filters', () => {
        let filters =
            {
                'cat':
                    {
                        'entities':
                            [
                                {
                                    'translated': {'name': 'cat'},
                                    'options': [
                                        {'id': 'Men', 'translated': {'name': 'Men'}},
                                        {'id': 'Hats', 'translated': {'name': 'Hats'}},
                                        {'id': 'Shoes', 'translated': {'name': 'Shoes'}},
                                        {'id': 'Shirts', 'translated': {'name': 'Shirts'}},
                                        {'id': 'Cool Hats', 'translated': {'name': 'Cool Hats'}},
                                        {'id': 'Lame Hats', 'translated': {'name': 'Lame Hats'}}]
                                }
                            ]
                    }, 'vendor':
                    {'entities': []},
                'price': {'entities': []},
                'color': {'entities': []},
                'shipping_free': {'entities': []},
                'shoe-color': {'entities': []},
                'textile': {'entities': []},
                'width': {'entities': []}
            };
        filterCategorySelectPlugin.options.name = 'cat';
        filterCategorySelectPlugin.refreshDisabledState(filters);
        let checkboxes = document.querySelectorAll('.filter-category-select-checkbox');
        let isDisable;
        let activeItems = [];
        const properties = filters['cat'];
        const entities = properties.entities;
        const property = entities.find(entity => entity.translated.name === 'cat');
        if (property) {
            activeItems.push(...property.options);
        }
        let activeIds = activeItems.map(entity => entity.id);
        let disabledChecked = false;
        Iterator.iterate(checkboxes, (checkbox) => {
            let notActiveId = !activeIds.includes(checkbox.id);
            isDisable = notActiveId ? checkbox.disabled : false;
            if (!isDisable) {
                return isDisable;
            }
            let event = new Event('click');
            checkbox.dispatchEvent(event);
            disabledChecked = checkbox.checked;
        });
        expect(isDisable).toBe(true)


    })

});
