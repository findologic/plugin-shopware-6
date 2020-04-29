import template from './findologic-page.html.twig';
import './findologic-page.scss';

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
                const defaultConfig = this.$refs.configComponent.allConfigs.null;
                const salesChannelId = this.$refs.configComponent.selectedSalesChannelId;

                if (salesChannelId === null) {
                    this.shopkeyAvailable = !!this.config['FinSearch.config.shopkey'];
                    this.isActive = !!this.config['FinSearch.config.active'];
                } else {
                    this.shopkeyAvailable = !!this.config['FinSearch.config.shopkey'] || !!defaultConfig['FinSearch.config.shopkey'];
                    this.isActive = !!this.config['FinSearch.config.active'] || !!defaultConfig['FinSearch.config.active']
                }

                let regex = /^[A-F0-9]{32}$/;
                this.isValidShopkey = regex.test(this.config['FinSearch.config.shopkey']) !== false;
                if (this.shopkeyAvailable) {
                    let hashedShopkey = Utils.format.md5(this.config['FinSearch.config.shopkey']).toUpperCase();
                    if (this.isValidShopkey) {
                        this.httpClient
                            .get(`https://cdn.findologic.com/static/${ hashedShopkey }/config.json`)
                            .then((response) => {
                                if (!response.data.isStagingShop) {
                                    this.isStagingShop = true;
                                }
                            })
                            .catch((error) => {
                                this.isStagingShop = false;
                            });
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
        showTestButton() {
            return this.isActive && this.isValidShopkey && this.isStagingShop;
        }
    },
    methods: {
        openSalesChannelDomain() {
            if (this.$refs.configComponent.selectedSalesChannelId !== null) {
                const criteria = new Criteria();
                criteria.addFilter(
                    Criteria.equals('id', this.$refs.configComponent.selectedSalesChannelId)
                );
                criteria.setLimit(1);
                criteria.addAssociation('domains');
                this.salesChannelRepository.search(criteria, Shopware.Context.api).then((searchresult) => {
                    let url = searchresult.first().domains.first().url.replace(/\/$/, '');
                    window.open(`${ url }?findologic=on`, "_blank");
                });
            } else {
                let url = window.location.origin.replace(/\/$/, '');
                window.open(`${ url }?findologic=on`, "_blank");
            }
        },
        onSave() {
            if (!this.shopkeyAvailable) {
                this.setErrorStates();
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.fieldRequired'),
                });

                return;
            }

            if (!this.isValidShopkey) {
                this.setErrorStates();
                this.createNotificationError({
                    title: this.$tc('findologic.settingForm.titleError'),
                    message: this.$tc('findologic.invalidShopkey'),
                });

                return;
            }

            this.save();
        },

        save() {
            this.isLoading = true;

            this.$refs.configComponent.save().then((res) => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                if (res) {
                    this.config = res;
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        setErrorStates() {
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
            } else {
                this.shopkeyErrorState = null;
            }
        }
    }

});
