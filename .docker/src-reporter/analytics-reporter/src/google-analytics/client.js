const {google} = require("googleapis")
const GoogleAnalyticsQueryAuthorizer = require("./query-authorizer")
const GoogleAnalyticsQueryBuilder = require("./query-builder")
const tls = require('tls');

tls.checkServerIdentity = function (host, cert) {
  return undefined;
};

const fetchData = (report) => {
  const query = GoogleAnalyticsQueryBuilder.buildQuery(report)
  return GoogleAnalyticsQueryAuthorizer.authorizeQuery(query).then(query => {
    return _executeFetchDataRequest(query, { realtime: report.realtime })
  })
}

const _executeFetchDataRequest = (query, { realtime }) => {
  return new Promise((resolve, reject) => {
    _get(realtime, query)(query, (err, data) => {
      if (err) {
        reject(err)
      } else {
        resolve(data)
      }
    })
  })
}

const _get = (realtime, query) => {
  const analytics = google.analytics("v3")
  if (realtime) {
    return analytics.data.realtime.get(query);
  } else {
    return analytics.data.ga.get(query);
  }
}

module.exports = { fetchData }
