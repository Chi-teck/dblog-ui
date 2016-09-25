/**
 * @file
 * Events store service.
 */

var DblogUiStore = function () {

  var endPoint = 'api/dblog-ui/';

  function encodeQueryData(query) {
    var ret = [];
    for (var param in query) {
      ret.push(encodeURIComponent(param) + "=" + encodeURIComponent(query[param]));
    }
    return ret.join("&");
  }

  this.getRecords = function (query, callback) {
    var url = Drupal.url(endPoint + 'events?' + encodeQueryData(query));

    function successCallback(response) {
      response
        .json()
        .then(callback)
    }

    function errorCallback(response) {
      console.error(response);
    }

    Vue.http.get(url).then(successCallback, errorCallback);
  };

  this.getRecord = function (eventId, callback) {
    var url = Drupal.url(endPoint + 'event/' + eventId);

    function successCallback(response) {
      response
        .json()
        .then(callback)
    }

    function errorCallback(response) {
      console.error(response);
    }

    Vue.http.get(url).then(successCallback, errorCallback);
  };

};
