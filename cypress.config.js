const { defineConfig } = require('cypress')
const getCompareSnapshotsPlugin = require('cypress-image-diff-js/dist/plugin')

module.exports = defineConfig({
  e2e: {
    setupNodeEvents(on, config) {
      // Cypress image diff plugin
      getCompareSnapshotsPlugin(on, config)
    },
    baseUrl: 'http://localhost',
  },
});