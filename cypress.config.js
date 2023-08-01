const { defineConfig } = require('cypress')
const getCompareSnapshotsPlugin = require('cypress-image-diff-js/dist/plugin')

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost',
    "retries": {
      "runMode": 2,
      // "openMode": 0
    },
    chromeWebSecurity: false,
    responsetimeout: 10000,
    "blockHosts": ["www.google-analytics.com", "ssl.google-analytics.com"],
    setupNodeEvents(on, config) {
      // Cypress image diff plugin
      getCompareSnapshotsPlugin(on, config),
      on('task', {
        log(message) {
          console.log(message)

          return null
        },
        table(message) {
          console.table(message)
    
          return null
        }
      })
    }
  },
});