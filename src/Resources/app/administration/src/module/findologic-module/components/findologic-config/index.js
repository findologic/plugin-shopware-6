import template from './findologic-config.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('findologic-config', {
  template,
  name: 'FindologicConfig',

  inject: ['repositoryFactory'],

  mixins: [
    Mixin.getByName('notification')
  ],

  props: {
    actualConfigData: {
      required: true
    },
    allConfigs: {
      type: Object,
      required: true
    },
    shopkeyErrorState: {
      required: true
    },
    selectedSalesChannelId: {
      type: String,
      required: false,
      default: null
    },
    selectedSalesChannelNavigationCategoryId: {
          type: String,
          required: false,
          default: null
      },
    isStagingShop: {
      type: Boolean,
      required: true,
      default: false
    },
    isValidShopkey: {
      type: Boolean,
      required: true,
      default: false
    },
    isActive: {
      type: Boolean,
      required: true,
      default: false
    },
    shopkeyAvailable: {
      type: Boolean,
      required: true,
      default: false
    }
  },

  data () {
    return {
      isLoading: false,
      term: null,
      categories: [],
      categoryIds: []
    };
  },

  created () {
    this.createdComponent();
  },

  methods: {
    createdComponent () {
      this.getCategories();
    },

    isString (value) {
      if (typeof value !== 'string') {
        return true;
      }
    },

    isBoolean (value) {
      return typeof value !== 'boolean';
    },

    /**
     * @public
     * @param result
     * @param prop
     * @param order
     * @returns {function(*, *): number}
     */
    sortByProperty (result, prop = 'name', order = 'asc') {

      result.sort(function (a, b) {
        // Use toUpperCase() to ignore character casing
        const case1 = typeof a[prop] === 'string' ? a[prop].toUpperCase() : a[prop];
        const case2 = typeof b[prop] === 'string' ? b[prop].toUpperCase() : b[prop];

        let sort = 0;
        if (case1 > case2) {
          sort = order === 'asc' ? 1 : -1;
        } else if (case1 < case2) {
          sort = order === 'asc' ? -1 : 1;
        }
        return sort;
      });

      return result;
    },

    /**
     * @public
     */
    openSalesChannelUrl () {
      if (this.selectedSalesChannelId !== null) {
        const criteria = new Criteria();
        criteria.addFilter(
          Criteria.equals('id', this.selectedSalesChannelId)
        );
        criteria.setLimit(1);
        criteria.addAssociation('domains');
        this.salesChannelRepository.search(criteria, Shopware.Context.api).then((searchresult) => {
          const domain = searchresult.first().domains.first();
          this._openStagingUrl(domain);
        });
      } else {
        this._openDefaultUrl();
      }
    },

    /**
     * @private
     */
    _openDefaultUrl () {
      const url = `${window.location.origin}?findologic=on`;
      window.open(url, '_blank');
    },

    /**
     * @param {Object} domain
     * @private
     */
    _openStagingUrl (domain) {
      if (domain) {
        const url = `${domain.url}?findologic=on`;
        window.open(url, '_blank');
      } else {
        this._openDefaultUrl();
      }
    },

    /**
     * @public
     */
    getCategories () {
      this.isLoading = true;

      const translatedCategories = [];
      this.categoryRepository.search(this.categoryCriteria, Shopware.Context.api).then((items) => {
        this.term = null;
        this.total = items.total;
        items.forEach((category) => {
          translatedCategories.push({
            value: category.id,
            name: category.name,
            label: category.translated.breadcrumb.join(' > ')
          });
        });

        this.categories = this.sortByProperty(translatedCategories, 'label');
      }).finally(() => {
        this.isLoading = false;
      });
    }
  },

  computed: {
    /**
     * @public
     * @returns {boolean}
     */
    showTestButton () {
      return this.isActive && this.shopkeyAvailable && this.isValidShopkey && this.isStagingShop;
    },

    showAPIConfig () {
      return this.integrationType === undefined || this.integrationType === 'API';
    },

    showDIConfig () {
      return this.integrationType === undefined || this.integrationType === 'Direct Integration';
    },

    filterPositionOptions () {
      return [
        {
          label: this.$tc('findologic.settingForm.config.filterPosition.top.label'),
          value: 'top'
        },
        {
          label: this.$tc('findologic.settingForm.config.filterPosition.left.label'),
          value: 'left'
        }];
    },

    mainVariantOptions() {
      return [
        {
          label: this.$tc('findologic.settingForm.config.mainVariant.default.label'),
          value: 'default'
        },
        {
          label: this.$tc('findologic.settingForm.config.mainVariant.parent.label'),
          value: 'parent'
        },
        {
          label: this.$tc('findologic.settingForm.config.mainVariant.cheapest.label'),
          value: 'cheapest'
        }
      ];
    },

    integrationType() {
      return this.actualConfigData['FinSearch.config.integrationType'];
    },

    salesChannelRepository () {
      return this.repositoryFactory.create('sales_channel');
    },

    categoryRepository () {
      return this.repositoryFactory.create('category');
    },

    categoryCriteria () {
      const criteria = new Criteria(1, 500);
      criteria.addSorting(Criteria.sort('name', 'ASC'));
      criteria.addSorting(Criteria.sort('parentId', 'ASC'));
      criteria.addFilter(Criteria.contains('path', this.selectedSalesChannelNavigationCategoryId));

      if (this.term) {
        criteria.addFilter(Criteria.contains('name', this.term));
      }

      return criteria;
    }
  }
});
