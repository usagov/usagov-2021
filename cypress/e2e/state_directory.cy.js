describe('State Directory', () => {
    beforeEach(() => {
        cy.visit('/life-events') 
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

                cy.wrap(el)
                    .visit('data-value')
            })
    })
})