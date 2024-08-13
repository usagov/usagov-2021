describe('Federal Directory [ENG]', () => {
    beforeEach(() => {
        cy.visit('/agency-index')
    })

    it('BTE 48: Landing page: letter name navigation', () => {
        // test navigating with letter names
        cy.get('ul.usagov-directory-container-az')
            .find('li')
            .should('have.length', 22)

        cy.get('ul.usagov-directory-container-az')
            .find('li')
            .find('a')
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
