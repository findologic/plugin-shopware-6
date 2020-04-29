import './page/findologic-page';
import './components/findologic-config';

import enGB from './snippet/en-GB';
import deDE from './snippet/de-DE';

const { Module } = Shopware;

Module.register('findologic-module', {
    type: 'plugin',
    name: 'FinSearch',
    title: 'findologic.header',
    description: 'findologic.general.mainMenuDescription',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#f7ff0f',
    icon: 'small-search',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'findologic-page'
                    },
                    path: 'index'
                    }
                    },

                    navigation: [{
                        id: 'findologic-module',
                        label: 'findologic.header',
                        color: '#f7ff0f',
                        path: 'findologic.module.index',
                        icon: 'small-search',
                        position: 100
                    }]
                    });
