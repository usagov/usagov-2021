describe('Nav Card Page [ES]', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/es/desastres-emergencias')

        cy.injectAxe()
    })
    it('BTS 20: Banner image appears on topic pages', () => {
        cy.get('#block-usagov-content')
            .find('.usagov-hero')
            .should('be.visible')
            .should('have.css', 'background-image')
    })
    it('BTS 21: Most popular links appear and function correctly on topic pages', () => {
        cy.get('.usagov-hero__callout')
            .should('be.visible')

        cy.get('.usagov-hero__callout')
            .find('a')
            .each((el) => {
                // Validate link
                cy.wrap(el)
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
            })
    })
    it('BTS 22: Cards on nav card page appear/function correctly on topic pages', () => {
        cy.get('.usagov-cards')
            .find('li')
            .each((el) => {
                // Validate link
                cy.wrap(el).find('a')
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
                
                // Verify number of children
                cy.wrap(el).find('a')
                    .children()
                    .should('have.length', 2)

                // CSS check for hover state 
                cy.wrap(el).find('a')
                    .realHover()
                    .should('have.css', 'background-color', 'rgb(204, 236, 242)')
            })
    })
    it('BTS 23: False children items appear as cards on on topic pages', () => {
        cy.visit('/es/servicios-personas-con-discapacidades')

        // Should have cards for SSDI, ADA, and voter laws
        let falseChildren = [
            'Beneficios del Seguro Social por incapacidad y para personas con una discapacidad', 
            'Sus derechos bajo la Ley para Estadounidenses con Discapacidades (ADA)', 
            'Leyes de accesibilidad para votantes'
        ]
        
        for (let i = 0; i < falseChildren.length; i++) {
            cy.get('.usagov-cards')
                .contains(falseChildren[i])
                .should('be.visible')
        }
    })
})