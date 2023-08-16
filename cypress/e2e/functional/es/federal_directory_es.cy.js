describe('Federal Directory [ES]', () => {
    beforeEach(() => {
        cy.visit('/es/indice-agencias') 
    })

    it('BTS 48: Landing page: letter name navigation', () => {
        // test navigating with letter names
        cy.get('ul.usagov-directory-container-az')
            .find('li')
            .should('have.length', 22)
        
        cy.get('ul.usagov-directory-container-az')
            .find('li')
            .each((el) => {
                cy.wrap(el)
                    .should('have.attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
            })
    })


})