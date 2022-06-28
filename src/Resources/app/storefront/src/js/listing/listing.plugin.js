export default class FlListingPlugin extends window.PluginManager.getPlugin('Listing').get('class') {
    init() {
        this.lastHash = window.location.hash;

        super.init();
    }

    /**
   * @private
   */
    _onWindowPopstate() {
    // Listing events should not be triggered/listened to on Direct Integration pages,
    // as Findologic replaces the Shopware listing, which may lead to unexpected behavior.
        if (this._isDirectIntegrationPage()) {
            return;
        }

        super._onWindowPopstate();
    }

    /**
   * @private
   */
    _isDirectIntegrationPage() {
        const lastHash = this.lastHash;
        const hash = this.lastHash = window.location.hash;

        const isSearchPage = hash.startsWith('#search:');
        const isNavigationPage = hash.startsWith('#navigation:');
        const isMSSOpened = hash.startsWith('#suggest:');
        const wasMSSOpened = lastHash.startsWith('#suggest:');
        const browserBackOnMobileSearch = hash.length === 0 && lastHash.startsWith('#search:');

        return isSearchPage || isNavigationPage || isMSSOpened || wasMSSOpened || browserBackOnMobileSearch;
    }
}
