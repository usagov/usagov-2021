describe('State Directory [ES]', () => {
    beforeEach(() => {
        cy.visit('/es/gobiernos-estatales') 
    })

    it('BTS 50/52: Landing page: state drop-down', () => {
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
    it('BTS 51: Test Alaska Page', () => {
        cy.visit('es/estados/alaska')

        // Test links on page.
        cy.get('div.State-Directory-Table').find('a').then(regLink => {
            cy.get(regLink[0]).should('have.attr', 'href').and('include', 'http://alaska.gov/')
            cy.get(regLink[1]).should('have.attr', 'href').and('include', 'https://gov.alaska.gov/contact')
        })
    })

})