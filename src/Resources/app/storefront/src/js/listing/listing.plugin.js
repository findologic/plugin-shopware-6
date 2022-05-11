export default class FlListingPlugin extends window.PluginManager.getPlugin('Listing').get('class') {

export default class FlListingPlugin extends ListingPlugin {
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
    const hash = window.location.hash;

    const isSearchPage = hash.startsWith('#search:');
    const isNavigationPage = hash.startsWith('#navigation:');
    const isMSSOpened = hash.startsWith('#suggest:');

    return isSearchPage || isNavigationPage || isMSSOpened;
  }
}
