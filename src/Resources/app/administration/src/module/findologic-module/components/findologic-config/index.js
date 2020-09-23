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
      type: Object,
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
      isLoading: false
    };
  },

  methods: {
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

    salesChannelRepository () {
      return this.repositoryFactory.create('sales_channel');
    },

    showAPIConfig () {
      return this.actualConfigData['FinSearch.config.integrationType'] === undefined || this.actualConfigData['FinSearch.config.integrationType'] === 'API';
    },

    showDIConfig () {
      return this.actualConfigData['FinSearch.config.integrationType'] === undefined || this.actualConfigData['FinSearch.config.integrationType'] === 'Direct Integration';
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
    }

  }
});
