const subpaths = require('../../fixtures/subpaths.json')

describe('Visual testing for English site', () =>{
    subpaths.eng.forEach((subpath) => {
        it(`Compare full page screenshot for http://localhost/${subpath}`, () => {
            cy.visit(`http://localhost/${subpath}`, {failOnStatusCode: false})

            const retryOptions = {
                limit: 0, // max number of retries
                delay: 500 // delay before next iteration, ms
            }
            
            // Threshold: 0.0
            cy.compareSnapshot(subpath, 0.0, retryOptions)
        })
    })
})