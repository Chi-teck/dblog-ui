/**
 * @file
 * DBlog UI behaviors.
 */

(function (url, settings, store) {

  'use strict';

  /* global Vue */
  /* global VuePager */
  Vue.component('pager', VuePager);

  Vue.component('table-sort-indicator', Vue.extend({
    template: '#dblog-ui-table-sort-indicator',
    props: {
      order: null,
      default: false
    },
    computed: {
      sort: function () {
        return this.$route.query.sort;
      },
      show: function () {
        return this.$route.query.order ? this.$route.query.order === this.order : this.default;
      },
      sortClass: function () {
        return 'tablesort tablesort--' + (this.$route.query.sort === 'asc' ? 'desc' : 'asc');
      }
    }
  }));

  Vue.directive('t', function (event) {
    event.innerHTML = Drupal.t(event.innerHTML.trim());
  });

  // Overview page.
  var List = Vue.extend({

    template: '#dblog-ui-list',

    mounted: function () {
      this.updateData();
    },

    data: function () {
      return {
        events: [],
        loading: false,
        totalPages: 1,
        type: [],
        typeOptions: [],
        severity: [],
        severityOptions: settings.severityLevels
      };
    },

    computed: {
      typePath: function () {
        return this.buildPath('type');
      },
      datePath: function () {
        return this.buildPath('date');
      },
      userPath: function () {
        return this.buildPath('user');
      }
    },

    watch: {
      $route: 'updateData'
    },

    methods: {

      updateData: function () {
        this.loading = true;
        var that = this;
        store.getRecords(this.$route.query, function (data) {
          that.totalPages = Math.ceil(data.total / 50);
          that.typeOptions = data.typeOptions;
          that.events = data.data;
          that.loading = false;
        });
      },

      filter: function () {
        this.$router.push(this.buildPath());
      },

      buildPath: function (order) {

        var query = {};
        Object.keys(this.$route.query).forEach(param => {
          query[param] = this.$route.query[param];
        })

        // Reset querystring.
        // Set to NULL, UNDEFINED or empty string does not work, so we delete it.
        if (order) {
          query.order = order;
          query.sort = !query.sort || query.sort === 'desc' ? 'asc' : 'desc';
        }
        else {
          delete query.order;
          delete query.sort;
        }

        if (this.type.length) {
          query.type = this.type;
        }
        else {
          delete query.type;
        }

        if (this.severity.length) {
          query.severity = this.severity;
        }
        else {
          delete query.severity;
        }

        return {
          path: '/',
          query: query
        };
      },

      reset: function () {
        this.type = [];
        this.severity = [];
        this.filter();
      },

      activeClass: function (order) {
        return this.$route.query.order === order ? 'is-active' : '';
      }
    }

  });

  // Event details page.
  var Details = Vue.extend({
    template: '#dblog-ui-details',

    mounted: function () {
      var that = this;
      this.loading = true;
      store.getRecord(this.$route.params.eventId, function (data) {
        that.event = data;
        that.loading = false;
      });
    },

    data: function () {
      return {
        loading: false,
        severityLevels: settings.severityLevels,
        event: {}
      };
    }

  });

  /* global VueRouter */

  var router = new VueRouter({
    base: drupalSettings.path.baseUrl + drupalSettings.path.currentPath,
    routes: [
      { path: '/', component: List },
      { path: '/event/:eventId', name: 'details', component: Details }
    ]
  })

  var app = new Vue({
    router: router,
    template: '<router-view></router-view>'
  }).$mount('#dblog-ui-app');

  /* global DblogUiStore */
}(Drupal.url, drupalSettings.dblogUi, new DblogUiStore()));


// Translatable stings.
/*
 Drupal.t('Filter log message');
 Drupal.t('Type');
 Drupal.t('Date');
 Drupal.t('Message');
 Drupal.t('User');
 Drupal.t('Operations');
 Drupal.t('Location');
 Drupal.t('Referrer');
 Drupal.t('Severity');
 Drupal.t('Hostname');
 Drupal.t('Back to overview page');
 Drupal.t('Sort ascending');
 Drupal.t('Sort descending')
 Drupal.t('Loading');
*/
