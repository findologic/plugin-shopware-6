import './extension/sw-plugin';
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
    color: '#f7ff0f',
    icon: 'small-search',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'findologic-page',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
