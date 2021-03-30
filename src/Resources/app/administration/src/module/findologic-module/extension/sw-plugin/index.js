import template from './sw-self-maintained-extension-card.html.twig';

const { Component } = Shopware;

// TODO: Find a fallback for versions lower than 6.4.
// TODO: Check if we manually need to override sw-extension-card-bought as well.
Component.override('sw-self-maintained-extension-card', {
  template,
  mounted() {
    if (this.extension.name === 'FinSearch') {
      this.extension.configurable = true;
    }
  },
  computed: {
    extensionCardClasses() {
      return {
        'sw-self-maintained-extension-card': true,
        ...this.$super('extensionCardClasses')
      };
    },
  }
})
