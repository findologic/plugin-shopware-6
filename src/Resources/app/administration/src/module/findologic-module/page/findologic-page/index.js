import template from './findologic-page.html.twig';
import './findologic-page.scss';

const { Component, Mixin, Application } = Shopware;

Component.register('findologic-page', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            isStagingShop: false,
            isValidShopkey: false,
            isRegisteredShopkey: null,
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
                const defaultConfig = this.$refs.configComponent.allConfigs.null;
                const salesChannelId = this.$refs.configComponent.selectedSalesChannelId;

                if (salesChannelId === null) {
                    this.shopkeyAvailable = !!this.config['FinSearch.config.shopkey'];
                    this.isActive = !!this.config['FinSearch.config.active'];
                } else {
                    this.shopkeyAvailable = !!this.config['FinSearch.config.shopkey']
                        || !!defaultConfig['FinSearch.config.shopkey'];
                    this.isActive = !!this.config['FinSearch.config.active'] || !!defaultConfig['FinSearch.config.active'];
                }

                // Check if shopkey is entered and according to schema
                if (this.shopkeyAvailable) {
                    const shopkey = this._getShopkey();
                    if (this._isShopkeyValid(shopkey)) {
                        this.shopkeyErrorState = null;
                        this._isStagingRequest(shopkey);
                    }
                }
                this._setErrorStates();
            },
            deep: true
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
            const regex = /^[A-F0-9]{32}$/;
            this.isValidShopkey = regex.test(shopkey) !== false;

            return this.isValidShopkey;
        },

        /**
         * @returns {String}
         * @private
         */
        _getShopkey() {
            const defaultConfig = this.$refs.configComponent.allConfigs.null;
            let shopkey = this.config['FinSearch.config.shopkey'];
            const hasShopkey = !!shopkey;
            // If shopkey is not entered, we check for default config in case of "inherited" shopkey
            if (!hasShopkey) {
                shopkey = defaultConfig['FinSearch.config.shopkey'];
            }

            return shopkey;
        },

        /**
         * @param {String} shopkey
         * @private
         */
        _isStagingRequest(shopkey) {
            this.httpClient
                .get(`https://cdn.findologic.com/config/${shopkey}/config.json`)
                .then((response) => {
                    if (response.data.isStagingShop) {
                        this.isStagingShop = true;
                    }
                })
                .catch(() => {
                    this.isStagingShop = false;
                });
        },

        /**
         * @public
         */
        onSave() {
            // If shopkey available but not according to schema, we do not call the validate service.
            // If shopkey is valid, we will check if it is registered, otherwise we allow empty shopkey to be saved
            if (this.shopkeyAvailable && !this.isValidShopkey) {
                this._setErrorStates(true);
                return;
            } else if(this.shopkeyAvailable) {
                this._validateShopkeyFromService().then((status) => {
                    this.isRegisteredShopkey = status;
                }).then(() => {
                    if (!this.isRegisteredShopkey) {
                        this._setErrorStates(true);
                    }

                });
            }

            this._save();
        },

        /**
         * @private
         */
        _save() {
            this.$refs.configComponent.save().then((res) => {
                this.shopkeyErrorState = null;
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
                this.isSaveSuccessful = false;
                this.isLoading = false;
            });
        },

        /**
         * @param {Boolean} withNotification
         * @private
         */
        _setErrorStates(withNotification = false) {
            this.isLoading = false;
            if (!this.shopkeyAvailable) {
                this.shopkeyErrorState = {
                    code: 1,
                    detail: this.$tc('findologic.fieldRequired')
                };
            } else if (!this.isValidShopkey) {
                this.shopkeyErrorState = {
                    code: 1,
                    detail: this.$tc('findologic.invalidShopkey')
                };
            } else if (this.isRegisteredShopkey === false) {
                this.shopkeyErrorState = {
                    code: 1,
                    detail: this.$tc('findologic.notRegisteredShopkey')
                };
            } else {
                this.shopkeyErrorState = null;
            }

            if (withNotification) {
                this._showNotification();
            }
        },

        /**
         * @private
         */
        _showNotification() {
            if (!this.shopkeyAvailable) {
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.fieldRequired')
                });
            } else if (!this.isValidShopkey) {
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.invalidShopkey')
                });
            } else if (this.isRegisteredShopkey === false) {
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.notRegisteredShopkey')
                });
            }
        },

        /**
         * @private
         */
        _validateShopkeyFromService() {
            this.isLoading = true;
            return this.httpClient
                .get(`https://account.findologic.com/api/v1/shopkey/validate/${this._getShopkey()}`)
                .then((response) => {
                    const status = String(response.status);
                    return status.startsWith('2');
                })
                .catch(() => {
                    return false;
                });
        }
    }
});
