import template from './sw-self-maintained-extension-card.html.twig';
import legacyTemplate from './sw-plugin-list.html.twig';

const { Component, State } = Shopware;

// Shopware >= 6.4
// eslint-disable-next-line
Component.override('sw-self-maintained-extension-card', {
    template,
    computed: {
        extensionCardClasses() {
            return {
                'sw-self-maintained-extension-card': true,
                ...this.$super('extensionCardClasses'),
            };
        },
    },
    mounted() {
        if (this.extension.name === 'FinSearch' && !this.supportsOpenAppMechanism()) {
            this.extension.configurable = true;
        }
    },
    methods: {
        supportsOpenAppMechanism() {
            return !!State.get('extensionEntryRoutes');
        },
    },
});

// Shopware < 6.4
// eslint-disable-next-line
Component.override('sw-plugin-list', {
    template: legacyTemplate,
});
