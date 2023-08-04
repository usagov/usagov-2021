describe('Life Events: Dealing With the Death of a Loved One', () => {
    beforeEach(() => {
        cy.visit('/death-loved-one') 
    })

    it('Landing page: dealing with the death of a loved one', () => {
        // image cards appear
        cy.get('div.usagov-cards')
                .find('ul')
                .should('be.visible')
        
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 6)

    })

    it('Topic page: report a death', () => {
        // test first (/report-a-death) card 
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
            .url().should('include', '/report-a-death')
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

    it('Topic page: social security report a death', () => {
        // test second (/social-security-report-a-death) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/social-security-report-a-death')
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

    it('Topic page: death certificate', () => {
        // test third (/death-certificate) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/death-certificate')
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

    it('Topic page: survivor benefits', () => {
        // test fourth (/survivor-benefits) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/survivor-benefits')
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

    it('Topic page: burial survivor benefits', () => {
        // test fifth (/burial-survivor-benefits) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/burial-survivor-benefits')
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
    
    it('Topic page: military records', () => {
        // test sixth (/military-records) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/military-records')
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

    it('Topic page: death abroad', () => {
        // test seventh (/death-abroad) card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(6)
            .find('a')
            .click()
            .url().should('include', '/death-abroad')
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