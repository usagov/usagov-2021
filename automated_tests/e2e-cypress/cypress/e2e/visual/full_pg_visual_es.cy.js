const subpaths = require('../../fixtures/subpaths.json')

describe('Visual testing for Spanish site', () =>{
    subpaths.es.forEach((subpath) => {

        it(`Compare full page screenshot for ${subpath}`, () => {
            cy.visit(subpath, {failOnStatusCode: false})

            const retryOptions = {
                limit: 0, // max number of retries
                delay: 500 // delay before next iteration, ms
            }

            cy.compareSnapshot({
		name: subpath,
		testThreshold: 0.0,
		recurseOptions: retryOptions
	    })
        })
    })
})
