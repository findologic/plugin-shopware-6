import template from './findologic-page.html.twig';
import './findologic-page.scss';

const {Component, Mixin, Application} = Shopware;
const {Criteria} = Shopware.Data;

Component.register('findologic-page', {
    template,

    inject: ['repositoryFactory', 'FinsearchConfigApiService', 'systemConfigApiService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            isStagingShop: false,
            shopkeyExists: false,
            isRegisteredShopkey: null,
            isActive: false,
            config: null,
            allConfigs: {},
            selectedSalesChannelId: null,
            selectedLanguageId: null,
            salesChannel: [],
            language: [],
            shopkeyErrorState: null,
            httpClient: Application.getContainer('init').httpClient
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    created() {
        this.createdComponent();
    },

    beforeMount() {
        this.updateHeaderBarBackLink();
    },

    watch: {
        selectedLanguageId: {
            handler(languageId) {
                if (!languageId) {
                    return;
                }

                this.createdComponent();
            }
        },

        shopkey() {
            this.shopkeyErrorState = null;
            if (this.isValidShopkey) {
                this._isStagingRequest();
            } else if (this.actualConfigData) {
                this.actualConfigData['FinSearch.config.active'] = false;
                this.actualConfigData['FinSearch.config.activeOnCategoryPages'] = false;
            }

            this._setErrorStates();
        }
    },

    computed: {
        configKey() {
            if (!this.selectedSalesChannelId || !this.selectedLanguageId) {
                return null;
            }

            return this.selectedSalesChannelId + '-' + this.selectedLanguageId;
        },

        actualConfigData: {
            get() {
                return this.allConfigs[this.configKey];
            },
            set(config) {
                this.allConfigs = {
                    ...this.allConfigs,
                    [this.configKey]: config
                };
            }
        },

        /**
         * @returns {String}
         * @private
         */
        shopkey() {
            return this.actualConfigData ? this.actualConfigData['FinSearch.config.shopkey'] : '';
        },

        isValidShopkey() {
            // Validate the shopkey
            const regex = /^[A-F0-9]{32}$/;

            return regex.test(this.shopkey) !== false;
        },

        shopkeyAvailable() {
            return !!this.shopkey;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        findologicConfigRepository() {
            return this.repositoryFactory.create('finsearch_config');
        }
    },

    methods: {
        createdComponent() {
            if ((this.selectedSalesChannelId && this.selectedLanguageId)) {
                this.isLoading = true;
                this.readAll().then((values) => {
                    if (!values['FinSearch.config.filterPosition']) {
                        values['FinSearch.config.filterPosition'] = 'top';
                    }
                    if (!values['FinSearch.config.mainVariant']) {
                        values['FinSearch.config.mainVariant'] = 'default';
                    }

                    this.actualConfigData = values;
                    this.isLoading = false;
                });
            }

            if (!this.salesChannel.length) {
                this.isLoading = true;
                let criteria = new Criteria();
                criteria.addAssociation('languages');
                criteria.addAssociation('domains');
                criteria.addFilter(Criteria.equals('active', true));

                this.salesChannelRepository.search(criteria, Shopware.Context.api).then(res => {
                    this.isLoading = false;
                    this.salesChannel = res;
                });
            }
        },

        /**
         * Updates the header bar link, based on the used Shopware version.
         *
         * * Shopware >= 6.4 => Extension list.
         * * Shopware < 6.4 => Settings page.
         */
        updateHeaderBarBackLink() {
            let foundModule = null;
            Shopware.Module.getModuleRegistry().forEach((module) => {
                if (foundModule) {
                    return;
                }

                if (module.manifest.title === 'sw-extension-store.title') {
                    foundModule = module;
                }
            });

            if (foundModule) {
                this.$route.meta.parentPath = 'sw.extension.my-extensions.listing.app';
                this.$route.meta.$module = foundModule.manifest;
            }
        },

        async readAll() {
            return await this.FinsearchConfigApiService.getValues(this.selectedSalesChannelId, this.selectedLanguageId);
        },

        /**
         * @private
         */
        _isStagingRequest() {
            this.httpClient
                .get(`https://cdn.findologic.com/config/${this.shopkey}/config.json`)
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
            } else if (this.shopkeyAvailable) {
                this._validateShopkeyFromService()
                    .then((status) => {
                        this.isRegisteredShopkey = status;
                    })
                    .then(() => {
                        if (!this.isRegisteredShopkey) {
                            this._setErrorStates(true);
                        } else {
                            this._save();
                        }
                    });
            } else {
                this._save();
            }
        },

        /**
         * @private
         */
        _save() {
            this.isLoading = true;
            this.isSaveSuccessful = false;
            this.FinsearchConfigApiService.batchSave(this.allConfigs).then((res) => {
                this.shopkeyExists = false;
                this.shopkeyErrorState = null;
                this.isLoading = false;
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    title: this.$tc('findologic.settingForm.titleSuccess'),
                    message: this.$tc('findologic.settingForm.configSaved')
                });

                if (res) {
                    this.actualConfigData = res;
                }
            }).catch((e) => {
                this.isSaveSuccessful = false;
                this.isLoading = false;
                this.shopkeyExists = true;
                this._setErrorStates(true);
            });
        },

        /**
         * @param {Boolean} withNotification
         * @private
         */
        _setErrorStates(withNotification = false) {
            this.isLoading = false;
            if (!this.shopkeyAvailable) {
                // Do nothing
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
            } else if (this.shopkeyExists === true) {
                this.shopkeyErrorState = {
                    code: 1,
                    detail: this.$tc('findologic.shopkeyExists')
                };
            } else {
                this.shopkeyExists = false;
                this.isRegisteredShopkey = true;
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
            } else if (this.shopkeyExists === true) {
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.shopkeyExists')
                });
            }
        },

        /**
         * @private
         */
        _validateShopkeyFromService() {
            this.isLoading = true;
            return this.httpClient
                .get(`https://account.findologic.com/api/v1/shopkey/validate/${this.shopkey}`)
                .then((response) => {
                    this.isLoading = false;
                    const status = String(response.status);
                    return status.startsWith('2');
                })
                .catch(() => {
                    return false;
                });
        },

        onSelectedLanguage(languageId) {
            this.shopkeyErrorState = null;
            this.selectedLanguageId = languageId;
            this.createdComponent();
        },

        onSelectedSalesChannel(salesChannelId) {
            this.language = [];
            if (this.salesChannel === undefined || salesChannelId === null) {
                this.onSelectedLanguage(null);
                return;
            }

            let selectedChannel = this.salesChannel.find(item => item.id === salesChannelId);
            if (selectedChannel) {
                this.selectedSalesChannelId = salesChannelId;
                this.onSelectedLanguage(selectedChannel.languageId);
                selectedChannel.languages.forEach((language) => {
                    const domain = selectedChannel.domains.find(item => item.languageId === language.id);
                    if (domain) {
                        if (domain.url !== null && domain.url !== '') {
                            this.language.push({
                                name: language.name,
                                label: language.name,
                                value: language.id
                            });
                        }
                    }
                });
            }
        }
    }
});
