// Display fail messages in a table located in the terminal after test is run
function terminalLog(violations) {
    cy.task(
      'log',
      `${violations.length} accessibility violation${
        violations.length === 1 ? '' : 's'
      } ${violations.length === 1 ? 'was' : 'were'} detected`
    )

    // Track specific keys to keep the table readable.
    const violationData = violations.map(
      ({ id, impact, description, nodes }) => ({
        id,
        impact,
        description,
        nodes: nodes.length
      })
    )
  
    cy.task('table', violationData)
}
  
const subpaths = require('../../fixtures/subpaths.json')

describe('Validate 508 accessibility compliance', () =>{
  subpaths.eng.forEach((subpath) => {
    it(`Run axe core http://localhost/${subpath}`, () => {
        cy.visit(`http://localhost/${subpath}`, {failOnStatusCode: false})
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
            `Run axe core http://localhost/${subpath}`
        )
        cy.checkA11y(null, null, terminalLog)
    })
  })
})