describe('Scam Report [ES]', () => {
    beforeEach(() => {
        cy.visit('/es/donde-reportar-una-estafa') 
    })
    // **UNFINISHED**
    it('BTS 47: allows for imposter reporting', () => {
        cy.get('#block-usagov-content')
            .find('a')
            .click()
            .get('#block-usagov-content')
            .find('#identity-theft').should('have.value', '/where-report-scams/what-type-scam-do-you-need-report/identity-theft#block-usagov-content')
            
    })


})