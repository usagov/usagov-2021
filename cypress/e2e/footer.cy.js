describe('Footer', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/')
    })
    it('Footer links appear and work appropriately', () => {
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
})