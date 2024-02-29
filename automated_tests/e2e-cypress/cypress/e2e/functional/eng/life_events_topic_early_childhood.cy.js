describe('Life Events: Early Childhood [ENG]', () => {
    beforeEach(() => {
        cy.visit('/early-childhood') 
    })

    it('BTE 54: Topic page looks correct, cards appear', () => {
        // image cards appear
        cy.get('div.usagov-cards')
                .find('ul')
                .should('be.visible')
        
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 6)

    })

    it('BTE 55: Topic page: food assistance', () => {
        // test first (/food-assistance) card 
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/food-assistance')
            .get('div.usa-card__container')
            .find('div.usa-card__media')
            .find('img').should(($img) => {
                expect($img).to.have.attr('src')
                expect($img).to.have.attr('alt')
            })
            .get('div.usa-card__container')
            .find('header')
            .should('be.visible')
            .find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
    })

    it('BTE 55: Topic page: food stamps', () => {
        // test second (/food-stamps) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/food-stamps')
            .get('div.usa-card__container')
            .find('div.usa-card__media')
            .find('img').should(($img) => {
                expect($img).to.have.attr('src')
                expect($img).to.have.attr('alt')
            })
            .get('div.usa-card__container')
            .find('header')
            .should('be.visible')
            .find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
    })

    it('BTE 55: Topic page: welfare benefits', () => {
        // test third (/welfare-benefits) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/welfare-benefits')
            .get('div.usa-card__container')
            .find('div.usa-card__media')
            .find('img').should(($img) => {
                expect($img).to.have.attr('src')
                expect($img).to.have.attr('alt')
            })
            .get('div.usa-card__container')
            .find('header')
            .should('be.visible')
            .find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
    })

    it('BTE 55: Topic page: medicaid', () => {
        // test fourth (/medicaid-chip-insurance) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/medicaid-chip-insurance')
            .get('div.usa-card__container')
            .find('div.usa-card__media')
            .find('img').should(($img) => {
                expect($img).to.have.attr('src')
                expect($img).to.have.attr('alt')
            })
            .get('div.usa-card__container')
            .find('header')
            .should('be.visible')
            .find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
    })

    it('BTE 55: Topic page: child support', () => {
        // test fifth (/child-support) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/child-support')
            .get('div.usa-card__container')
            .find('div.usa-card__media')
            .find('img').should(($img) => {
                expect($img).to.have.attr('src')
                expect($img).to.have.attr('alt')
            })
            .get('div.usa-card__container')
            .find('header')
            .should('be.visible')
            .find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
    })
    
    it('BTE 55: Topic page: social security', () => {
        // test sixth (/social-security-card) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/social-security-card')
            .get('div.usa-card__container')
            .find('div.usa-card__media')
            .find('img').should(($img) => {
                expect($img).to.have.attr('src')
                expect($img).to.have.attr('alt')
            })
            .get('div.usa-card__container')
            .find('header')
            .should('be.visible')
            .find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
    })

})