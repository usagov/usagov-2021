describe('State Directory', () => {
    beforeEach(() => {
        cy.visit('/state-governments') 
    })

    it('Landing page: state drop-down', () => {
        // testing dropdown menu
        cy.get('#block-usagov-content')
            .find('[id=stateForm]')
            .find('ul')
            .should('[role=listbox]')
            .each((el) => {
                cy.wrap(el)
                    .invoke('data-value')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
            })
    })
    it('Test Alaska Page', () => {
        cy.visit('/states/alaska')

        // Test links on page.
        cy.get('div.State-Directory-Table').find('a').then(regLink => {
            cy.get(regLink[0]).should('have.attr', 'href').and('include', 'https://alaska.gov/')
            cy.get(regLink[1]).should('have.attr', 'href').and('include', 'https://gov.alaska.gov/')
            cy.get(regLink[2]).should('have.attr', 'href').and('include', 'https://gov.alaska.gov/contact/')
        })
    })

})