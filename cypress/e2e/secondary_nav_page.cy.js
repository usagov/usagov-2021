describe('Secondary Nav Page', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/disaster-financial-help')

        cy.injectAxe()
    })
    it('Has no critical impact accessibility violations on load', () => {
        // Test on initial load, only report and assert for critical impact items
        cy.checkA11y(null, {
          includedImpacts: ['critical']
        })
    })
    it('BTE 24: Links/cards to content appear in the main body of the page and behave as expected', () => {
        cy.get('.usagov-navpage-item')
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
                cy.wrap(el)
                    .realHover()
                    .should('have.css', 'background-color', 'rgb(210, 235, 241)')
            })
    })
    it('BTE 25: Left menu appears on page', () => {
        cy.get('.usa-sidenav')
            .should('be.visible')
    })
    it('BTE 26: Breadcrumb appears at top of page and indicates correct section', () => {
        cy.get('.usa-breadcrumb__list')
            .find('li')
            .first()
            .contains('Home')

        // Breadcrumb indicates correct section
        cy.get('.usa-breadcrumb__list')
            .find('li')
            .last()
            .invoke('text')
            .then((breadcrumb) => {
              // Grab page title and compare to breadcrumb text
              cy.get('h1')
                .invoke('text')
                .should((pageTitle) => {
                    expect(breadcrumb.trim()).to.include(pageTitle.trim())
                })
            })
    })
    it.only('False children items appear as a link', () => {
        cy.visit('/visit-united-states')

        // TODO
    })
})