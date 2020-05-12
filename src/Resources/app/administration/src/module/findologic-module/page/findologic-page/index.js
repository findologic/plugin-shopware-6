import template from './findologic-page.html.twig';

const { Component, Mixin, Application, Utils } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('findologic-page', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            isStagingShop: false,
            isValidShopkey: false,
            isActive: false,
            shopkeyAvailable: false,
            config: null,
            shopkeyErrorState: null,
            httpClient: Application.getContainer('init').httpClient
        };
    },
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
    watch: {
        config: {
            handler() {
                const defaultConfig = this.$refs.configComponent.allConfigs['null'];
                const salesChannelId = this.$refs.configComponent.selectedSalesChannelId;

                if (salesChannelId === null) {
                    this.shopkeyAvailable = !!this.config['FinSearch.config.shopkey'];
                    this.isActive = !!this.config['FinSearch.config.active'];
                } else {
                    this.shopkeyAvailable = !!this.config['FinSearch.config.shopkey'] || !!defaultConfig['FinSearch.config.shopkey'];
                    this.isActive = !!this.config['FinSearch.config.active'] || !!defaultConfig['FinSearch.config.active']
                }

                // Check if shopkey is entered
                if (this.shopkeyAvailable) {
                    let shopkey = this._getValidShopkey();
                    if (this._isShopkeyValid(shopkey)) {
                        let hashedShopkey = Utils.format.md5(shopkey).toUpperCase();
                        this._isStagingRequest(hashedShopkey);
                    }
                }
            },
            deep: true
        }
    },
    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
        /**
         * @public
         * @returns {boolean}
         */
        showTestButton() {
            return this.isActive && this.shopkeyAvailable && this.isValidShopkey && this.isStagingShop;
        }
    },
    methods: {
        /**
         * @param {String} shopkey
         * @returns {boolean}
         * @private
         */
        _isShopkeyValid(shopkey) {
            // Validate the shopkey
            let regex = /^[A-F0-9]{32}$/;
            this.isValidShopkey = regex.test(shopkey) !== false;

            return this.isValidShopkey;
        },

        /**
         * @returns {String}
         * @private
         */
        _getValidShopkey() {
            const defaultConfig = this.$refs.configComponent.allConfigs['null'];
            let shopkey = this.config['FinSearch.config.shopkey'];
            let hasShopkey = !!shopkey;
            // If shopkey is not entered, we check for default config in case of "inherited" shopkey
            if (!hasShopkey) {
                shopkey = defaultConfig['FinSearch.config.shopkey'];
            }

            return shopkey;
        },

        /**
         * @param {String} hashedShopkey
         * @private
         */
        _isStagingRequest(hashedShopkey) {
            this.httpClient
            .get('https://cdn.findologic.com/static/' + hashedShopkey + '/config.json')
            .then((response) => {
                if (response.data.isStagingShop) {
                    this.isStagingShop = true;
                }
            })
            .catch((error) => {
                this.isStagingShop = false;
            });
        },

        /**
         * @public
         */
        openSalesChannelUrl() {
            if (this.$refs.configComponent.selectedSalesChannelId !== null) {
                const criteria = new Criteria();
                criteria.addFilter(
                    Criteria.equals('id', this.$refs.configComponent.selectedSalesChannelId)
                );
                criteria.setLimit(1);
                criteria.addAssociation('domains');
                this.salesChannelRepository.search(criteria, Shopware.Context.api).then((searchresult) => {
                    let domain = searchresult.first().domains.first();
                    this._openStagingUrl(domain);
                });
            } else {
                this._openDefaultUrl();
            }
        },

        /**
         * @public
         */
        onSave() {
            this._setErrorStates();
            if (!this.shopkeyAvailable || !this.isValidShopkey) {
                return;
            }

            this._save();
        },

        /**
         * @private
         */
        _save() {
            this.isLoading = true;

            this.$refs.configComponent.save().then((res) => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    title: this.$tc('findologic.settingForm.titleSuccess'),
                    message: this.$tc('findologic.settingForm.configSaved')
                });
                if (res) {
                    this.config = res;
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        /**
         * @private
         */
        _setErrorStates() {
            if (!this.shopkeyAvailable) {
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.fieldRequired')
                });
                this.shopkeyErrorState = {
                    code: 1,
                    detail: this.$tc('findologic.fieldRequired')
                };
            } else if (!this.isValidShopkey) {
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.invalidShopkey')
                });
                this.shopkeyErrorState = {
                    code: 1,
                    detail: this.$tc('findologic.invalidShopkey')
                };
            } else {
                this.shopkeyErrorState = null;
            }
        },

        /**
         * @private
         */
        _openDefaultUrl() {
            let url = window.location.origin + '?findologic=on';
            window.open(url, '_blank');
        },

        /**
         * @param {Object} domain
         * @private
         */
        _openStagingUrl(domain) {
            if (domain) {
                let url = domain.url + '?findologic=on';
                window.open(url, '_blank');
            } else {
                this._openDefaultUrl();
            }
        }
    }
});
