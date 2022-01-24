import ListingPlugin from 'src/plugin/listing/listing.plugin';

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

  _isDirectIntegrationPage() {
    const hash = window.location.hash;

    const isSearchPage = hash.startsWith('#search:search');
    const isNavigationPage = hash.startsWith('#navigation:search');
    const isMSSOpened = hash.startsWith('#suggest:suggest');

    return isSearchPage || isNavigationPage || isMSSOpened;
  }
}
