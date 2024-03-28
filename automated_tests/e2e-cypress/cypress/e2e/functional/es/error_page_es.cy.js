describe('Error Page [ES]', () => {
    beforeEach(() => {
        cy.visit('/es') 
    })
    it('BTS 56: Invalid url loads error page', () => {
        cy.request({url: '/invalidurl', failOnStatusCode: false})
            .its('status')
            .should('equal', 404)
            
        cy.visit('/invalidurl', {failOnStatusCode: false})
    })
        
})