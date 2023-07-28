describe('Mobile', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport(390, 844)
        cy.visit('/')
    })
    it('Full page visual test: Default page looks correct upon load', () => {
        // Hide back to top btn to account for different scroll speeds
        cy.get('#back-to-top').hideElement()
        cy.compareSnapshot('home-page-full', 0.1)
    })
    it.only('Mobile menu appears and functions appropriately', () => {
        // Open menu
        cy.get('.usa-menu-btn').click()

        // Menu looks correct
        cy.wait(500)
        cy.get('.usagov-mobile-menu')
            .compareSnapshot('home-page-menu', 0.1)

        // Close menu
        cy.get('.usagov-mobile-menu-top')
            .find('.usa-nav__close')
            .click()

        // Page looks correct, menu collapsed
        cy.wait(500)
        cy.compareSnapshot('home-page-full', 0.1)
    })
    it('Footer appears as expected on mobile, topics can be expanded and links function appropriately', () => {
        // Visually the default footer nav looks correct
        cy.get('.usa-footer__nav')
            .compareSnapshot('footer-nav-default', 0)
        
        cy.get('.usa-footer__nav')
            .find('.usa-footer__primary-content')
            .each((section, i) => {
                cy.wrap(section)
                    .find('.usa-list')
                    .should('not.be.visible')

                // Expand accordion 
                cy.wrap(section)
                    .find('.usa-gov-footer__primary-link')
                    .click()

                cy.wrap(section)
                    .find('.usa-list')
                    .should('be.visible')

                // Visually the footer nav looks correct, one section should be expanded
                /*cy.wait(500)
                cy.get('.usa-footer__nav')
                    .compareSnapshot(`footer-nav-expanded-section-${i}`, 0)*/

                // Validate links
                cy.wrap(section)
                    .find('a')
                    .not('[href="/website-analytics/"]')
                    .each((link) => {
                        cy.wrap(link).invoke('attr', 'href')
                            .then(href => {
                                cy.request(href)
                                    .its('status')
                                    .should('eq', 200)
                            })
                    })

                // Close accordion
                cy.wrap(section)
                    .find('.usa-gov-footer__primary-link')
                    .click()

                cy.wrap(section)
                    .find('.usa-list')
                    .should('not.be.visible')

                // Visually the footer nav looks correct, back to default
                cy.wait(500)
                cy.get('.usa-footer__nav')
                    .compareSnapshot('footer-nav-default', 0)
            })
    })
})