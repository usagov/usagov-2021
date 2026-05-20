const { defineConfig } = require('cypress')
const path = require('path')

const dependencyRoot = path.resolve(__dirname, '../cypress_build/node_modules')
const reporterPath = require.resolve('cypress-mochawesome-reporter', {
  paths: [dependencyRoot],
})
const reporterPluginPath = require.resolve('cypress-mochawesome-reporter/plugin', {
  paths: [dependencyRoot],
})
const reporterLibPath = require.resolve('cypress-mochawesome-reporter/lib', {
  paths: [dependencyRoot],
})
const imageDiffPluginPath = require.resolve('cypress-image-diff-js/plugin', {
  paths: [dependencyRoot],
})
const { beforeRunHook } = require(reporterLibPath)

module.exports = defineConfig({
  allowCypressEnv: false,
  env: {
    exampleHost: 'veronica.dev.local',
    exampleApiServer: 'http://localhost:8888/v1/',
  },
  reporter: reporterPath,
  video: false,
  screenshotOnRunFailure: true,
  e2e: {
    baseUrl: 'http://cms-usagov.docker.local', // CYPRESS_BASE_URL OS env var will override this.
    viewportWidth: 1280,
    viewportHeight: 800,
    "retries": {
      "runMode": 0,
      "openMode": 0
    },
    chromeWebSecurity: false,
    responsetimeout: 10000,
    blockHosts: [
      "www.google-analytics.com",
      "ssl.google-analytics.com",
      "*.googletagmanager.com",
      "www.googletagmanager.com",
      "tagmanager.google.com",
      "www.tagmanager.google.com"
    ],
    experimentalRunAllSpecs: true,
    setupNodeEvents(on, config) {

      // Plugins
      require(imageDiffPluginPath)(on, config);
      require(reporterPluginPath)(on);
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
