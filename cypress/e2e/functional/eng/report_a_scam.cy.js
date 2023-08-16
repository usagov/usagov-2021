describe('Scam Report [ENG]', () => {
    beforeEach(() => {
        cy.visit('/where-report-scams#block-wizardenglish') 
    })
    //**UNFINISHED**
    it('BTE 47: allows for imposter reporting', () => {
        cy.get('#block-usagov-content')
            .find('a')
            .click()
            .get('#block-usagov-content')
            .find('#identity-theft').should('have.value', '/where-report-scams/what-type-scam-do-you-need-report/identity-theft#block-usagov-content')
            
    })


})