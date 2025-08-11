const fs = require("fs");
const path = require("path");
const cheerio = require("cheerio");
const ResultTotalsCalculator = require("./result_totals_calculator");

// ---- simple on-disk cache at /tmp/titleCache.json
const CACHE_FILE = path.join("/tmp", "titleCache.json");
let titleCache = new Map();
try {
  if (fs.existsSync(CACHE_FILE)) {
    const raw = fs.readFileSync(CACHE_FILE, "utf-8");
    titleCache = new Map(Object.entries(JSON.parse(raw)));
    console.log(`Loaded ${titleCache.size} cached titles from ${CACHE_FILE}`);
  }
} catch (err) {
  console.warn("Could not load title cache:", err.message);
}

async function fetchPageTitle(url) {
  if (titleCache.has(url)) return titleCache.get(url);

  const controller = new AbortController();
  const t = setTimeout(() => controller.abort(), 10000); // 10s timeout

  try {
    const res = await fetch(url, {
      signal: controller.signal,
      redirect: "follow",
      headers: {
        "User-Agent":
          "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36",
        "Accept":
          "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9",
      },
    });

    const ctype = res.headers.get("content-type") || "";
    if (!res.ok || !ctype.includes("text/html")) {
      titleCache.set(url, null);
      return null;
    }

    const html = await res.text();
    const $ = cheerio.load(html);
    let title =
      $("head > title").first().text().trim() ||
      $('meta[property="og:title"]').attr("content") ||
      $('meta[name="title"]').attr("content") ||
      null;

    if (title) title = title.replace(/\s+/g, " ").trim();
    titleCache.set(url, title || null);
    return title || null;
  } catch {
    titleCache.set(url, null);
    return null;
  } finally {
    clearTimeout(t);
  }
}

// run tasks with a concurrency cap
async function runLimited(items, limit, worker) {
  const results = [];
  let i = 0;
  const runners = Array.from({ length: Math.min(limit, items.length) }, async () => {
    while (i < items.length) {
      const idx = i++;
      results[idx] = await worker(items[idx], idx);
    }
  });
  await Promise.all(runners);
  return results;
}

class AnalyticsDataProcessor {
  #hostname;
  #mapping = {
    activeUsers: "active_visitors",
    fileName: "file_name",
    fullPageUrl: "page",
    pageTitle: "page_title",
    unifiedScreenName: "page_title",
    sessions: "visits",
    deviceCategory: "device",
    operatingSystem: "os",
    operatingSystemVersion: "os_version",
    hostName: "domain",
    languageCode: "language_code",
    sessionSource: "source",
    sessionSourceMedium: "session_source_medium",
    eventName: "event_label",
    eventCount: "total_events",
    landingPagePlusQueryString: "landing_page",
    sessionDefaultChannelGroup: "session_default_channel_group",
    screenPageViews: "pageviews",
    totalUsers: "users",
    screenPageViewsPerSession: "pageviews_per_session",
    averageSessionDuration: "avg_session_duration",
    bounceRate: "bounce_rate",
    screenResolution: "screen_resolution",
    mobileDeviceModel: "mobile_device",
  };

  constructor(config) {
    this.#hostname = config.account.hostname;
  }

  /**
   * @param {Object} report The report object that was requested
   * @param {Object} data The response object from the Google Analytics Data API
   * @param {Object} query The query object for the report
   * @returns {Object} The response data transformed to flatten the data
   * structure, format dates, and map from GA keys to DAP keys. Data is filtered
   * as requested in the report object. This object also includes details from
   * the original report and query.
   */
  async processData(report, data, query) {
    let result = this.#initializeResult({ report, data, query });

    // If you use a filter that results in no data, you get null
    // back from google and need to protect against it.
    if (!data || !data.rows) {
      return result;
    }

    // Some reports may decide to cut fields from the output.
    if (report.cut) {
      data = this.#removeColumnFromData({ column: report.cut, data });
    }

    // Remove data points that are below the threshold if one exists
    if (report.threshold) {
      data = this.#filterRowsBelowThreshold({
        threshold: report.threshold,
        data,
      });
    }

    // 1) Build all rows synchronously (no network)
    result.data = data.rows.map((row) => this.#processRow({ row, report, data }));

    // 2) Totals
    result.totals = ResultTotalsCalculator.calculateTotals(result, {
      sumVisitsByColumns: report.sumVisitsByColumns,
    });

    // 3) Fetch titles only for TOP 10 by total_events
    //    (keep GA title for others)
    const top10 = [...result.data]
      .filter((d) => d.linkUrl)
      .sort(
        (a, b) =>
          parseInt(b.total_events || "0", 10) - parseInt(a.total_events || "0", 10)
      )
      .slice(0, 10);

    await runLimited(top10, 5, async (item) => {
      const title = await fetchPageTitle(item.linkUrl);
      if (title) {
        item.page_title = title;
        // optional debug:
        // console.log("Title override ✓", item.linkUrl, "→", JSON.stringify(title));
      }
    });

    // 4) Persist cache to disk
    try {
      fs.writeFileSync(
        CACHE_FILE,
        JSON.stringify(Object.fromEntries(titleCache), null, 2)
      );
      // console.log(`Saved ${titleCache.size} titles to ${CACHE_FILE}`);
    } catch (err) {
      console.warn("Could not save title cache:", err.message);
    }

    return result;
  }

  #fieldNameForColumnIndex({ entryKey, index, data }) {
    // data keys come back as values for the header keys
    const targetKey = entryKey.replace("Values", "Headers");
    const name = data[targetKey][index].name;
    return this.#mapping[name] || name;
  }

  #filterRowsBelowThreshold({ threshold, data }) {
    data = Object.assign({}, data);

    const column = this.#findDimensionOrMetricIndex(threshold.field, data);
    if (column != null) {
      data.rows = data.rows.filter((row) => {
        return (
          parseInt(row[column.rowKey][column.index].value) >=
          parseInt(threshold.value)
        );
      });
    }

    return data;
  }

  /**
   * If dimension or metric is found matching the provided name, then return an
   * object with rowKey matching the key in row where the value can be found and
   * index of the named value.  If no match is found, return null.
   */
  #findDimensionOrMetricIndex(name, data) {
    const dimensionIndex = data.dimensionHeaders.findIndex((header) => {
      return header.name === name;
    });

    if (dimensionIndex === -1) {
      const metricIndex = data.metricHeaders.findIndex((header) => {
        return header.name === name;
      });

      if (metricIndex === -1) {
        return null;
      } else {
        return { rowKey: "metricValues", index: metricIndex };
      }
    } else {
      return { rowKey: "dimensionValues", index: dimensionIndex };
    }
  }

  #formatDate(date) {
    if (date == "(other)") {
      return date;
    }
    return [date.substr(0, 4), date.substr(4, 2), date.substr(6, 2)].join("-");
  }

  #initializeResult({ report, data, query }) {
    return {
      name: report.name,
      sampling: data.metadata?.samplingMetadatas,
      query: ((query) => {
        query = Object.assign({}, query);
        delete query.ids;
        return query;
      })(query),
      meta: report.meta,
      data: [],
      totals: {},
      taken_at: new Date(),
    };
  }

  #processRow({ row, data }) {
    const point = {};

    // Iterate through each entry in the object
    for (const [entryKey, entryValue] of Object.entries(row)) {
      // Iterate through each object in the array
      entryValue.forEach((item, index) => {
        // Iterate through each key-value pair in the object
        for (const [key, value] of Object.entries(item)) {
          if (key !== "oneValue") {
            const field = this.#fieldNameForColumnIndex({
              entryKey,
              index,
              data,
            });

            let modValue;

            if (field === "date") {
              modValue = this.#formatDate(value);
            } else {
              modValue = value;
            }

            point[field] = modValue;
          }
        }
      });
    }

    if (this.#hostname && !("domain" in point)) {
      point.domain = this.#hostname;
    }

    return point;
  }

  #removeColumnFromData({ column, data }) {
    data = Object.assign(data);

    const columnToRemove = this.#findDimensionOrMetricIndex(column, data);

    if (columnToRemove != null) {
      data[columnToRemove.rowKey.replace("Values", "Headers")].splice(
        columnToRemove.index,
        1
      );
      data.rows.forEach((row) => {
        row[columnToRemove.rowKey].splice(columnToRemove.index, 1);
      });
    }

    return data;
  }
}

module.exports = AnalyticsDataProcessor;
