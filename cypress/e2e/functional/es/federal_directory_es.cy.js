describe('Federal Directory', () => {
    beforeEach(() => {
        cy.visit('/es/indice-agencias') 
    })

    it('Landing page: letter name navigation', () => {
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