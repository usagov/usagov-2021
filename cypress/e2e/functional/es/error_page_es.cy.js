describe('Error Page (spanish)', () => {
    beforeEach(() => {
        cy.visit('/es') 
    })
    it('Invalid url loads error page', () => {
        cy.request({url: '/invalidurl', failOnStatusCode: false})
            .its('status')
            .should('equal', 404)
            
        cy.visit('/invalidurl', {failOnStatusCode: false})
    })
        
})