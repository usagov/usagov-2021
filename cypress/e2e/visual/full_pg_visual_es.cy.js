const subpaths = require('../../fixtures/subpaths.json')

describe('Visual testing for English site', () =>{
    subpaths.es.forEach((subpath) => {
        it(`Compare full page screenshot for http://localhost/${subpath}`, () => {
            cy.visit(`http://localhost/${subpath}`, {failOnStatusCode: false})
            
            // Threshold: 0.0
            cy.compareSnapshot(subpath, 0.0)
        })
    })
})