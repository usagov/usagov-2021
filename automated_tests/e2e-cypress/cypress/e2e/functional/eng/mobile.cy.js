describe('Mobile [ENG]', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport(390, 844)
        cy.visit('/')

        cy.injectAxe()
    })
    it('BTE 17: Mobile menu appears and functions appropriately', () => {
        cy.get('.usagov-mobile-menu')
            .should('not.be.visible')

        // Open menu
        cy.get('.usa-menu-btn').click()

        cy.get('.usagov-mobile-menu')
            .should('be.visible')

        // Validate menu links
        cy.get('.navigation__items')
            .find('a')
            .each((link) => {
                cy.wrap(link).invoke('attr', 'href')
                .then(href => {
                    cy.request(href)
                        .its('status')
                        .should('eq', 200)
                })
            })

        // Close menu
        cy.get('.usagov-mobile-menu-top')
            .find('.usa-nav__close')
            .click()

        cy.get('.usagov-mobile-menu')
            .should('not.be.visible')
    })
    it('BTE 18: Search appears in mobile menu and functions approriately', () => {
        const typedText = 'housing'

        // Open menu
        cy.get('.usa-menu-btn').click()

        // Enters query into search input
        cy.get('header')
            .find('#search-field-small-mobile-menu')
            .type(typedText)
            .should('have.value', typedText)
            .type('{enter}')

        // Origin URL should now be search.gov
        const sentArgs = { query: typedText }
        cy.origin(
            'https://search.usa.gov/',
            { args: sentArgs },
            ({ query }) => {
                cy.get('#search-field').should('have.value', query)
            }
        )

        // Go back to localhost to test search icon
        cy.visit('/')
        cy.get('.usa-menu-btn').click()

        cy.get('header')
            .find('#search-field-small-mobile-menu')
            .next()
            .find('img')
            .should('have.attr', 'alt', 'Search')

        cy.get('header')
            .find('#search-field-small-mobile-menu')
            .next()
            .click()

        // Verify URL is search.gov
        cy.origin('https://search.usa.gov/', () => {
            cy.url().should('include', 'search.usa.gov')
        })
    })
    it('BTE 19: Footer appears as expected on mobile, topics can be expanded and links function appropriately', () => {
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
            })
    })
})