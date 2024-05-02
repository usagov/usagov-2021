describe('Home Page - Visual', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/')
    })
    it('Sitewide banner for official government site appears at the top, accordion can be expanded', () => {
        const retryOptions = {
            limit: 0, // max number of retries
            delay: 500 // delay before next iteration, ms
        }

        cy.get('header')
            .find('.usa-banner__header')
            .should('be.visible')

        // Default accordion should look correct
        // Accordion content should not be visible
        cy.get('header')
            .find('.usa-banner')
            .compareSnapshot({name: 'accordion-default',
			      testThreshold: 0,
			      recurseOptions: retryOptions
			     })

        // Expand accordion
        cy.get('header')
            .find('.usa-accordion__button')
            .click()

        // Expanded accordion should look correct
        // Accordion content should be visible
        cy.get('header')
            .find('.usa-banner')
            .compareSnapshot({
		name: 'accordion-expanded',
		testThreshold: 0.1,
		recurseOptions: retryOptions
	    })
    })
    it('Life experiences carousel appears; can navigate through it to see all content (both arrows and circle indicator); can click cards and go to appropriate topic', () => {
        const retryOptions = {
            limit: 0, // max number of retries
            delay: 500 // delay before next iteration, ms
        }

        const num_events = 6
        const num_visible = 3

        // Carousel looks correct, should start at default positioning
        cy.get('.life-events-carousel')
            .compareSnapshot({
		name: 'life-events-carousel-default',
		testThreshold: 0.05,
		recurseOptions: retryOptions
	    })

        /*
         * Testing arrow buttons
         */

        // Click through slides using arrow buttons
        cy.get('.life-events-carousel')
            .find('.slide')
            .filter('[aria-hidden="true"]')
            .each((el, i) => {
                // Click next button
                cy.get('.life-events-carousel')
                    .find('.next')
                    .click()

                // Visual check
                cy.wait(500)
                cy.get('.life-events-carousel')
                    .compareSnapshot({
			name: `life-events-carousel-next-${i}`,
			testThreshold: 0.05,
			recurseOptoins: retryOptions
		    })
            })

        // Click prev button back to the front
        for (let i = 0; i < num_events - num_visible; i++) {
            cy.get('.life-events-carousel')
                .find('.previous')
                .click()
        }

        // Visual check, carousel should be back at default
        cy.wait(500)
        cy.get('.life-events-carousel')
            .compareSnapshot({
		name: 'life-events-carousel-default',
		testThreshold: 0.05,
		recurseOptions: retryOptions
	    })

        /*
         * Testing nav circles
         */

        // Click last nav circle
        cy.get('.carousel__navigation_button')
            .last()
            .click()

        // Visual check, carousel should be at the end
        cy.wait(500)
        cy.get('.life-events-carousel')
            .compareSnapshot({
		name: 'life-events-carousel-end',
		testThreshold: 0.05,
		recurseOptions: retryOptions
	    })

        // Run through each nav button
        cy.get('.carousel__navigation_button')
            .each((el, i) => {
                // Skip to reduce num screenshots
                if (i % 2 == 0) {
                    cy.wrap(el).click()

                    // Visual check
                    cy.wait(500)
                    cy.get('.life-events-carousel')
                        .compareSnapshot({
			    name: `life-events-carousel-nav-btn-${i}`,
			    testThreshold: 0.05,
			    recurseOptions: retryOptions
			})
                }
            })
    })
})
