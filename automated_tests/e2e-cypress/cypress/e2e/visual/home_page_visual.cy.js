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
            .compareSnapshot('accordion-default', 0, retryOptions)

        // Expand accordion
        cy.get('header')
            .find('.usa-accordion__button')
            .click()

        // Expanded accordion should look correct
        // Accordion content should be visible
        cy.get('header')
            .find('.usa-banner')
            .compareSnapshot('accordion-expanded', 0.1, retryOptions)
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
            .compareSnapshot('life-events-carousel-default', 0.05, retryOptions)
           
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
                    .compareSnapshot(`life-events-carousel-next-${i}`, 0.05, retryOptions)
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
            .compareSnapshot('life-events-carousel-default', 0.05, retryOptions)
        
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
            .compareSnapshot('life-events-carousel-end', 0.05, retryOptions)
        
        // Run through each nav button
        cy.get('.carousel__navigation_button')
            .each((el, i) => {
                // Skip to reduce num screenshots
                if (i % 2 == 0) {
                    cy.wrap(el).click()

                    // Visual check
                    cy.wait(500)
                    cy.get('.life-events-carousel')
                        .compareSnapshot(`life-events-carousel-nav-btn-${i}`, 0.05, retryOptions)
                }
            })
    })
})