import template from './findologic-config.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @private
 */
Component.register('findologic-config', {
    name: 'findologic-config',
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        actualConfigData: {
            type: Array,
            required: true,
        },
        allConfigs: {
            type: Object,
            required: true,
        },
        shopkeyErrorState: {
            type: Object,
            required: true,
        },
        selectedSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        isStagingShop: {
            type: Boolean,
            required: true,
            default: false,
        },
        isValidShopkey: {
            type: Boolean,
            required: true,
            default: false,
        },
        isActive: {
            type: Boolean,
            required: true,
            default: false,
        },
        shopkeyAvailable: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    data() {
        return {
            isLoading: false,
            categoryCollection: [],
        };
    },

    computed: {
        /**
         * @public
         * @returns {boolean}
         */
        showTestButton() {
            return this.isActive && this.shopkeyAvailable && this.isValidShopkey && this.isStagingShop;
        },

        showAPIConfig() {
            return this.integrationType === undefined || this.integrationType === 'API';
        },

        showDIConfig() {
            return this.integrationType === undefined || this.integrationType === 'Direct Integration';
        },

        filterPositionOptions() {
            return [
                {
                    label: this.$tc('findologic.settingForm.config.filterPosition.top.label'),
                    value: 'top',
                },
                {
                    label: this.$tc('findologic.settingForm.config.filterPosition.left.label'),
                    value: 'left',
                }];
        },

        mainVariantOptions() {
            return [
                {
                    label: this.$tc('findologic.settingForm.config.mainVariant.default.label'),
                    value: 'default',
                },
                {
                    label: this.$tc('findologic.settingForm.config.mainVariant.parent.label'),
                    value: 'parent',
                },
                {
                    label: this.$tc('findologic.settingForm.config.mainVariant.cheapest.label'),
                    value: 'cheapest',
                    disabled: this.actualConfigData['FinSearch.config.advancedPricing'] !== 'off',
                },
            ];
        },

        advancedPricingOptions() {
            return [
                {
                    label: this.$tc('findologic.settingForm.config.advancedPricing.off.label'),
                    value: 'off',
                },
                {
                    label: this.$tc('findologic.settingForm.config.advancedPricing.cheapest.label'),
                    value: 'cheapest',
                },
                {
                    label: this.$tc('findologic.settingForm.config.advancedPricing.unit.label'),
                    value: 'unit',
                },
            ];
        },

        integrationType() {
            return this.actualConfigData['FinSearch.config.integrationType'];
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        selectedCategoriesCriteria() {
            const criteria = new Criteria(null, null);
            criteria.addFilter(Criteria.equalsAny('id', this.actualConfigData['FinSearch.config.crossSellingCategories']));

            return criteria;
        },
    },

    created() {
        this.createCategoryCollection();
    },

    methods: {
        async createCategoryCollection() {
            this.categoryCollection = this.actualConfigData['FinSearch.config.crossSellingCategories']?.length
                ? await this.categoryRepository.search(this.selectedCategoriesCriteria, Shopware.Context.api)
                : new EntityCollection(
                    this.categoryRepository.route,
                    this.categoryRepository.entityName,
                    Shopware.Context.api,
                );
        },

        /**
         * @public
         */
        openSalesChannelUrl() {
            if (this.selectedSalesChannelId !== null) {
                const criteria = new Criteria(null, null);
                criteria.addFilter(
                    Criteria.equals('id', this.selectedSalesChannelId),
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
        _openDefaultUrl() {
            const url = `${window.location.origin}?findologic=on`;
            window.open(url, '_blank');
        },

        /**
         * @param {Object} domain
         * @private
         */
        _openStagingUrl(domain) {
            if (domain) {
                const url = `${domain.url}?findologic=on`;
                window.open(url, '_blank');
            } else {
                this._openDefaultUrl();
            }
        },

        onCategoryAdd(item) {
            if (this.actualConfigData['FinSearch.config.crossSellingCategories']) {
                this.actualConfigData['FinSearch.config.crossSellingCategories'].push(item.id);
            } else {
                this.actualConfigData['FinSearch.config.crossSellingCategories'] = [item.id];
            }
        },

        onCategoryRemove(item) {
            this.actualConfigData['FinSearch.config.crossSellingCategories'] =
        this.actualConfigData['FinSearch.config.crossSellingCategories'].filter(categoryId => categoryId !== item.id);
        },

        onAdvancedPricingChange(newConfig) {
            const mainVariantConfig = this.actualConfigData['FinSearch.config.mainVariant'];

            if (newConfig !== 'off' && mainVariantConfig === 'cheapest') {
                this.actualConfigData['FinSearch.config.mainVariant'] = 'default';
            }
        },
    },
});
