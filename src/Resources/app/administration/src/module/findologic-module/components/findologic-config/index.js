import template from './findologic-config.html.twig';

const { Component } = Shopware;

Component.register('findologic-config', {
    template,
    name: 'FindologicConfig',

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
                }
                },

                methods: {
                    checkTextFieldInheritance(value) {
                        if (typeof value !== 'string') {
                            return true;
                        }

                        return value.length <= 0;
                    },

                    checkBoolFieldInheritance(value) {
                        return typeof value !== 'boolean';


                    }
                }
                });
