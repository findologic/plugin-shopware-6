const { Component } = Shopware;

const { Criteria, EntityCollection } = Shopware.Data;

Component.extend('fl-entity-multi-select', 'sw-entity-multi-select', {
  model: {
    prop: 'selectedEntityIds',
    event: 'change',
  },

  props: {
    selectedEntityIds: {
      type: Array,
      required: true,
    },

    // Not included for SW 6.2
    entityName: {
      type: String,
      required: false,
      default: null,
    },
  },

  computed: {
    repository() {
      return this.repositoryFactory.create(this.entityName || this.entityCollection.entity);
    },
  },

  methods: {
    emitChanges(newCollection) {
      const entitiesById = newCollection.map(entity => entity.id);
      this.$emit('change', entitiesById);
    },
    refreshCurrentCollection: async function() {
      this.isLoading = true;

      if (this.selectedEntityIds.length) {
        const searchCriteria = new Criteria(1, this.selectedEntityIds.length || 0);
        searchCriteria.setIds(this.selectedEntityIds);

        this.currentCollection = await this.repository.search(searchCriteria, { ...this.context, inheritance: true });
      } else {
        this.currentCollection = new EntityCollection(
          this.repository.route,
          this.entityName,
          this.context,
          { ...this.context, inheritance: true },
        );
      }

      this.isLoading = false;
    },
  }
});
