const { defineConfig } = require('cypress')
const getCompareSnapshotsPlugin = require('cypress-image-diff-js/plugin')
const { beforeRunHook } = require('cypress-mochawesome-reporter/lib')

module.exports = defineConfig({
  reporter: 'cypress-mochawesome-reporter',
  video: false,
  screenshotOnRunFailure: true,
  e2e: {
    baseUrl: 'http://cms-usagov.docker.local', // CYPRESS_BASE_URL OS env var will override this.
    viewportWidth: 1280,
    viewportHeight: 800,
    "retries": {
      "runMode": 2,
      // "openMode": 0
    },
    chromeWebSecurity: false,
    responsetimeout: 10000,
    "blockHosts": ["www.google-analytics.com", "ssl.google-analytics.com"],
    experimentalRunAllSpecs: true,
    setupNodeEvents(on, config) {

      // Plugins
      require('cypress-image-diff-js/plugin')(on, config);
      require('cypress-mochawesome-reporter/plugin')(on);
      on('before:run', async (details) => {
        console.log('override before:run')
        await beforeRunHook(details)
      });
      // Tasks
      on('task', {
        log(message) {
          console.log(message)

          return null
        },
        table(message) {
          console.table(message)

          return null
        }
      });
      // return getCompareSnapshotsPlugin(on, config);
    },
  },
});
