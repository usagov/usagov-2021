describe('Life Events: Approaching Retirement', () => {
    beforeEach(() => {
        cy.visit('/approaching-retirement') 
    })

    it('Landing page: approaching retirement', () => {
        // image cards appear
        cy.get('div.usagov-cards')
                .find('ul')
                .should('be.visible')
        
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 6)

    })

    it('Topic page: what is social security', () => {
        // test first (/what-is-social-security) card 
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/what-is-social-security')
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

    it('Topic page: medicare', () => {
        // test second (/medicare) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/medicare')
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

    it('Topic page: senior food programs', () => {
        // test third (/senior-food-programs) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/senior-food-programs')
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

    it('Topic page: social security calculators', () => {
        // test fourth (/social-security-calculators) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/social-security-calculators')
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

    it('Topic page: retirement benefits locator', () => {
        // test fifth (/military-requirements) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/retirement')
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
    
    it('Topic page: retirement planning tools', () => {
        // test sixth (/retirement-planning-tools) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/retirement-planning-tools')
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

    it('Topic page: social security abroad', () => {
        // test seventh (/social-security-abroad) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(6)
            .find('a')
            .click()
            .url().should('include', '/social-security-abroad')
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