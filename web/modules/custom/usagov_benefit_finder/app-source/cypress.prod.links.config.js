const { defineConfig } = require('cypress')

module.exports = defineConfig({
  retries: {
    runMode: 2,
    openMode: 0,
  },
  e2e: {
    baseUrl: 'https://www.usa.gov',
    specPattern: 'cypress/e2e/usagov-public-site/links.cy.js',
  },
})
