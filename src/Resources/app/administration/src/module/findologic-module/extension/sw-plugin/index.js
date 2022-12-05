import template from './sw-self-maintained-extension-card.html.twig';

const { Component, State } = Shopware;

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
