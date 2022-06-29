export default class FilterCategorySelectElement  {
     ulClassList = 'filter-multi-select-list filter-category-select-list';
     liClassList = 'filter-multi-select-list-item filter-property-select-list-item ' +
                   'filter-category-select-list-item';
     divClassList = 'custom-control custom-checkbox category-filter-container';
     inputClassList = 'custom-control-input filter-category-select-checkbox';
     labelClassList = 'filter-multi-select-item-label filter-category-select-item-label' +
                      'custom-control-label';
     iconClassList = 'category-toggle-icon closed-icon'
     childDivClassList = 'custom-control custom-checkbox sub-item hide-category-list-item'
     childCheckboxClassList = 'custom-control-input filter-multi-select-checkbox ' +
                              'filter-category-select-checkbox'

     init() {
         return this;
     }

     /**
     * @param name
     * @returns {HTMLDivElement}
     */
     createMainCategory(name) {

         const div = document.createElement('div');
         div.classList = this.divClassList;

         const checkBox = document.createElement('input');
         checkBox.type = 'checkbox';
         checkBox.id = name;
         checkBox.value = name;
         checkBox.classList = this.inputClassList;

         const label = document.createElement('label');
         label.append('<span>'+name+'</span>');
         label.classList = this.labelClassList;

         div.append(checkBox);
         div.append(label);

         return div;
     }

     /**
     * @param name
     * @param parentCategoryName
     * @returns {HTMLDivElement}
     */
     createChildCategory(name, parentCategoryName) {
         const div = document.createElement('div');
         div.classList = this.childDivClassList;

         const input = document.createElement('input');
         input.id = parentCategoryName+'_'+name;
         input.type = 'checkbox';
         input.value = parentCategoryName+'_'+name;
         input.classList = this.childCheckboxClassList;

         const label = document.createElement('label');
         label.classList = this.labelClassList;
         label.append('<span>'+name+'</span>');
         label.classList = this.labelClassList;

         div.append(input);
         div.append(label);

         return div;
     }

     /**
     * @returns {HTMLUListElement}
     */
     createCategoryStructure() {
         const ul = document.createElement('ul');
         ul.classList = this.ulClassList;

         const menList = document.createElement('li');
         menList.classList = this.liClassList;

         const newcomersList = document.createElement('li');
         newcomersList.classList = this.liClassList;

         const womenList = document.createElement('li');
         womenList.classList = this.liClassList;

         const menIcon = document.createElement('label');
         menIcon.classList = this.iconClassList;

         const womenIcon = document.createElement('label');
         womenIcon.classList = this.iconClassList;

         const men = this.createMainCategory('Men');
         const women = this.createMainCategory('Women');

         const menHats = this.createChildCategory('Hats', 'Men');
         const menCoolHats = this.createChildCategory('Cool Hats', 'Men');
         const menLameHats = this.createChildCategory('Lame Hats', 'Men');

         menHats.append(menIcon);
         menHats.append(menCoolHats);
         menHats.append(menLameHats);

         const womenHats = this.createChildCategory('Hats', 'Women');
         const womenCoolHats = this.createChildCategory('Cool Hats', 'Women');
         const womenLameHats = this.createChildCategory('Lame Hats', 'Women');

         womenHats.append(womenIcon);
         womenHats.append(womenCoolHats);
         womenHats.append(womenLameHats);

         const menShirts = this.createChildCategory('Shirts', 'Men');
         const menShoes = this.createChildCategory('Shoes', 'Men');
         const womenShirts = this.createChildCategory('Shirts', 'Women');
         const womenShoes = this.createChildCategory('shoes', 'Women');
         const newComers = this.createMainCategory('Newcomers');

         men.append(menIcon);

         //Child categories
         men.append(menHats);
         men.append(menShirts);
         men.append(menShoes);

         women.append(womenIcon);

         //Child categories
         women.append(womenHats);
         women.append(womenShirts);
         women.append(womenShoes);

         menList.append(men);
         newcomersList.append(newComers);
         womenList.append(women);

         ul.append(menList);
         ul.append(newcomersList);
         ul.append(womenList);

         return ul;
     }
}
