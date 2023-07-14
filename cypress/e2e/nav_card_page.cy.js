describe('Nav Card Page', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/disability-services')
    })
    it.skip('Visual test test', () => {
        cy.compareSnapshot('nav-card-page-full', 0)
    })
})