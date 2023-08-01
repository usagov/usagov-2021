import { google } from "googleapis";
import { authorizeQuery } from "./query-authorizer";
import { buildQuery } from "./query-builder";
import { checkServerIdentity } from 'tls';

checkServerIdentity = function (host, cert) {
  return undefined;
};

const fetchData = async (report) => {
  const query = buildQuery(report)
  const query_2 = await authorizeQuery(query);
  return await _executeFetchDataRequest(query_2, { realtime: report.realtime });
}

const _executeFetchDataRequest = async (query, { realtime }) => {
  return new Promise((resolve, reject) => {
    _get(realtime)(query, (err, data) => {
      if (err) {
        reject(err)
      } else {
        resolve(data)
      }
    })
  })
}

const _get = async (realtime) => {
  const analytics = google.analytics("v3")
  if (realtime) {
    return await analytics.data.realtime.get(query);
  } else {
    return await analytics.data.ga.get(query);
  }
}

export default { fetchData }
