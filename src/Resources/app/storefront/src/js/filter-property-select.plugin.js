import FilterPropertySelectPlugin from 'src/plugin/listing/filter-property-select.plugin';

export default class FlFilterPropertySelectPlugin extends FilterPropertySelectPlugin {
    refreshDisabledState(filter) {
        // Prevent disabling if propertyName is not set correctly
        if (this.options.propertyName === '') {
            return;
        }

        const activeItems = [];
        const properties = filter[this.options.name];
        const entities = properties.entities;

        if (!entities || !entities.length) {
            this.disableFilter();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.propertyName);
        if (property) {
            activeItems.push(...property.options);
        } else {
            this.disableFilter();
            return;
        }

        const actualValues = this.getValues();

        if (activeItems.length < 1 && actualValues[this.options.name].length === 0) {
            this.disableFilter();
            return;
        }
        this.enableFilter();


        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }
}
