describe('Life Events: Recovering From a Disaster', () => {
    beforeEach(() => {
        cy.visit('/disaster') 
    })

    it('Landing page: recovering from a disaster', () => {
        // image cards appear
        cy.get('div.usagov-cards')
                .find('ul')
                .should('be.visible')
        
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 7)

    })

    it('Topic page: disaster housing shelter', () => {
        // test first (/disaster-housing-shelter) card 
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/disaster-housing-shelter')
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

    it('Topic page: disaster assistance', () => {
        // test second (/disaster-assistance) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/disaster-assistance')
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

    it('Topic page: disaster food help', () => {
        // test third (/disaster-food-help) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/disaster-food-help')
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

    it('Topic page: disaster help with bills', () => {
        // test fourth (/disaster-help-with-bills) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/disaster-help-with-bills')
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

    it('Topic page: disaster unemployment', () => {
        // test fifth (/disaster-unemployment) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/disaster-unemployment')
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
    
    it('Topic page: disaster mortgage', () => {
        // test sixth (/disaster-mortgage) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/disaster-mortgage')
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

    it('Topic page: disaster small business', () => {
        // test seventh (/disaster-small-business) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(6)
            .find('a')
            .click()
            .url().should('include', '/disaster-small-business')
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