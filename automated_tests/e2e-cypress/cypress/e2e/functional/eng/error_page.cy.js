describe('Error Page [ENG]', () => {
    beforeEach(() => {
        cy.visit('/') 
    })
    it('BTE 56: Invalid url loads error page', () => {
        cy.request({url: '/invalidurl', failOnStatusCode: false})
            .its('status')
            .should('equal', 404)
            
        cy.visit('/invalidurl', {failOnStatusCode: false})
    })
        
})