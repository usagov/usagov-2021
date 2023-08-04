describe('Life Events: Adulthood', () => {
    beforeEach(() => {
        cy.visit('/adulthood') 
    })

    it('Landing page: adulthood', () => {
        // image cards appear
        cy.get('div.usagov-cards')
                .find('ul')
                .should('be.visible')
        
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 7)

    })

    it('Topic page: register to vote', () => {
        // test first (/register-to-vote) card 
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/register-to-vote')
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

    it('Topic page: selective service', () => {
        // test second (/register-selective-service) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/register-selective-service')
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

    it('Topic page: financial aid', () => {
        // test third (/financial-aid) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/financial-aid')
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

    it('Topic page: job search', () => {
        // test fourth (/job-search) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/job-search')
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

    it('Topic page: military requirements', () => {
        // test fifth (/military-requirements) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/military-requirements')
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
    
    it('Topic page: file taxes', () => {
        // test sixth (/file-taxes) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/file-taxes')
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

    it('Topic page: apply adult passport', () => {
        // test seventh (/apply-adult-passport) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(6)
            .find('a')
            .click()
            .url().should('include', '/apply-adult-passport')
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