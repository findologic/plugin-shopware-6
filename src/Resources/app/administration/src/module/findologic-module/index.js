import './extension/sw-plugin';
import './page/findologic-page';
import './components/findologic-config';
import './components/fl-entity-multi-select';

import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';

const { Module } = Shopware;

Module.register('findologic-module', {
  type: 'plugin',
  name: 'FinSearch',
  title: 'findologic.header',
  description: 'findologic.general.mainMenuDescription',
  color: '#f7ff0f',

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
  },

  extensionEntryRoute: {
    extensionName: 'FinSearch',
    route: 'findologic.module.index'
  }
});
