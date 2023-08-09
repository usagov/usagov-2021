describe('Contact Elected Officials (Spanish)', () => {
    beforeEach(() => {
        cy.visit('/es/funcionarios-electos') 
    })

    it('allows for form to be filled out', () => {
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

        cy.get('.usa-accordion__button')
            .click()
    })
})