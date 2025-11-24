const socials = require('../../../fixtures/socials.json')

describe('Footer [ENG]', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/')
    })
    it('BTE 12: Footer links appear and work appropriately', () => {
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

    it('BTE 15: Contact Center information appears in footer and phone number links to /phone', () => {
        cy.get('#footer-phone')
            .find('a')
            .click()

        cy.url().should('include', '/phone')
    })
    it('BTE 16: Subfooter indicating USAGov is official site appears at very bottom', () => {
        cy.get('.usa-footer')
            .find('.usa-identifier')
            .should('contain', 'USAGov')
            .should('contain', 'official guide')
    })
})