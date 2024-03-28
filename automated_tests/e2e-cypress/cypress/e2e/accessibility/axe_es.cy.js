import terminalLog from './log_func'
const subpaths = require('../../fixtures/subpaths.json')

describe('Validate 508 accessibility compliance on Spanish site', () =>{
  subpaths.es.forEach((subpath) => {
    it(`Run axe core baseUrl/${subpath}`, () => {
        cy.visit(`/${subpath}`, {failOnStatusCode: false})
        cy.injectAxe()

        // Expand sitewide banner accordion
        cy.get('[class="usa-banner__button-text"]').click()

        cy.configureAxe({
            runOnly: {
                values: ['wcag2aa']
            }
        })

        cy.task(
            'log',
            `Run axe core baseUrl/${subpath}`
        )
        cy.checkA11y(
          null,
          // Only detects critical errors
          /*{
            includedImpacts: ['critical']
          },*/
          terminalLog)
    })
  })
})
