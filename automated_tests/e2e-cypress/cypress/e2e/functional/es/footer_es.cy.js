const socials = require('../../../fixtures/socials.json')

describe('Footer [ES]', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/es')
    })
    it('BTS 12: Footer links appear and work appropriately', () => {
        cy.get('.usa-footer__nav')
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
    })

    it('BTS 15: Contact Center information appears in footer and phone number links to /phone', () => {
        cy.get('#footer-phone')
            .find('a')
            .click()

        cy.url().should('include', '/es/llamenos')
    })
    it('BTS 16: Subfooter indicating USAGov is official site appears at very bottom', () => {
        cy.get('.usa-footer')
            .find('.usa-identifier')
            .should('contain', 'USAGov')
            .should('contain', 'la guía oficial')
        
        // Verify identifier footer links work (flexible about text content)
        cy.get('.usa-identifier__section--required-links')
            .find('a')
            .each((link) => {
                cy.wrap(link)
                    .invoke('attr', 'href')
                    .then((href) => {
                        cy.request(href).its('status').should('eq', 200)
                    })
            })
    })
})