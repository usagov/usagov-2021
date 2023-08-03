const {google} = require("googleapis")
const GoogleAnalyticsQueryAuthorizer = require("./query-authorizer")
const GoogleAnalyticsQueryBuilder = require("./query-builder")
const tls = require('tls');

const winston = require("winston-color")

tls.checkServerIdentity = function (host, cert) {
  return undefined;
};

const fetchData = async (report) => {
  const query = GoogleAnalyticsQueryBuilder.buildQuery(report)
  winston.debug(`[${report.name}] query:`, query)
  const query_2 = await GoogleAnalyticsQueryAuthorizer.authorizeQuery(query);
  winston.debug(`[${report.name}] query2:`, query2)
  return await _executeFetchDataRequest(query_2, { realtime: report.realtime });
}

const _executeFetchDataRequest = async (query, { realtime }) => {
  return new Promise((resolve, reject) => {
    _get(query, realtime)
      .then((data) => resolve(data))
      .catch((err) => reject(err));
  });
};

const _get = async (query, realtime) => {
  const analytics = google.analytics("v3")
  winston.debug(`[${report.name}] query in _get:`, query)
  if (realtime) {
    return await analytics.data.realtime.get(query);
  } else {
    return await analytics.data.ga.get(query);
  }
}

module.exports = { fetchData }
