describe('Contact Elected Officials [ENG]', () => {
    beforeEach(() => {
        cy.visit('/elected-officials') 
    })

    it('BTE 45: allows for form to be filled out', () => {
        // input values into form
        cy.get('#input-street')
            .type('1600 Pennsylvania Avenue NW')
            .get('#input-city')
            .type('Washington')
            .get('#input-state')
            .type('District of Columbia')
            .get('#input-state--list')
            .find('li')
            .click()
            .get('#input-zip')
            .type('20500')

        cy.get('button.usa-button--big')
            .click()
        // submit form
        cy.get('.usa-accordion__button')
            .click()
    })
})