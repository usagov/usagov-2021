describe('Life Events', () => {
    beforeEach(() => {
        cy.viewport('macbook-13')
        cy.visit('/es/etapas-importantes-de-la-vida') 
    })
    it('Landing page: banner image', () => {
        cy.get('#block-usagov-content')
            .find('section')
            .should('be.visible')
    })
    it('Landing page: image cards', () => {
        cy.get('div.usagov-cards')
            .find('ul')
            .should('be.visible')
    })
    it('Landing page: topic links', () => {
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 6)
        
        // test first card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/embarazo-primera-infancia')
            .go('back')

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/transicion-edad-adulta')
            .go('back')

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/prepararse-para-la-jubilacion')
            .go('back')

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/enfrentar-dificultades-economicas')
            .go('back')
        
        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/recuperarse-desastre-natural')
            .go('back')
        
        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/muerte-de-un-ser-querido')
            .go('back')
    })

})