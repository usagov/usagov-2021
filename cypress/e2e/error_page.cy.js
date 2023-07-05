describe('Error Page', () => {
    beforeEach(() => {
        cy.visit('/') 
    })
    it('Invalid url loads error page', () => {
        cy.request({url: '/invalidurl', failOnStatusCode: false})
            .its('status')
            .should('equal', 404)
            
        cy.visit('/invalidurl', {failOnStatusCode: false})
    })
        
})