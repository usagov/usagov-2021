describe('Life Events', () => {
    beforeEach(() => {
        cy.visit('/life-events') 
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
            .url().should('include', '/early-childhood')
            .go('back')

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/adulthood')
            .go('back')

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/approaching-retirement')
            .go('back')

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/financial-hardship')
            .go('back')
        
        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/disaster')
            .go('back')
        
        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/death-loved-one')
            .go('back')
    })

    it('Topic page: Early Childhood', () => {
        cy.visit('/early-childhood')
        
        // image cards appear
        cy.get('div.usagov-cards')
                .find('ul')
                .should('be.visible')
        
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 6)
        
        // test first (/food-assistance) card 
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/food-assistance')
            .get('div.usa-card__container')
            .get('div.usa-card__media')
            .find('img')
            .should('have.attr', 'src')
            .should('have.attr', 'alt')
            .get('header')
            .should('be.visible')
            .find('a')
            .click()
            .url().should('include', '/early-childhood')

        // test second (/food-stamps) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/food-stamps')
            .get('div.usa-card__container')
            .get('div.usa-card__media')
            .find('img')
            .should('have.attr', 'src')
            .should('have.attr', 'alt')
            .get('header')
            .should('be.visible')
            .find('a')
            .click()
            .url().should('include', '/early-childhood')

        // test third (/welfare-benefits) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/welfare-benefits')
            .get('div.usa-card__container')
            .get('div.usa-card__media')
            .find('img')
            .should('have.attr', 'src')
            .should('have.attr', 'alt')
            .get('header')
            .should('be.visible')
            .find('a')
            .click()
            .url().should('include', '/early-childhood')

        // test fourth (/medicaid-chip-insurance) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/medicaid-chip-insurance')
            .get('div.usa-card__container')
            .get('div.usa-card__media')
            .find('img')
            .should('have.attr', 'src')
            .should('have.attr', 'alt')
            .get('header')
            .should('be.visible')
            .find('a')
            .click()
            .url().should('include', '/early-childhood')
        
        // test fifth (/child-support) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/child-support')
            .get('div.usa-card__container')
            .get('div.usa-card__media')
            .find('img')
            .should('have.attr', 'src')
            .should('have.attr', 'alt')
            .get('header')
            .should('be.visible')
            .find('a')
            .click()
            .url().should('include', '/early-childhood')
        
        // test sixth (/social-security-card) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/social-security-card')
            .get('div.usa-card__container')
            .get('div.usa-card__media')
            .find('img')
            .should('have.attr', 'src')
            .should('have.attr', 'alt')
            .get('header')
            .should('be.visible')
            .find('a')
            .click()
            .url().should('include', '/early-childhood')
    })

})