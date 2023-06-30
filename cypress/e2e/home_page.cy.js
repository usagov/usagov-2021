describe('Home Page', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/') 
    })
    it('Sitewide banner for official government site appears at the top, accordion can be expanded', () => {
        cy.get('header')
            .find('.usa-banner__header')
            .should('be.visible')

        // Accordion content should not be visible
        cy.get('header')
            .find('.usa-banner__content')
            .should('not.be.visible')

        // Expand accordion
        cy.get('header')
            .find('.usa-accordion__button')
            .click()

        // Accordion content should be visible
        cy.get('.usa-banner__content')
            .should('be.visible')
    })
    it('USAGov logo appears in the header area', () => {
        cy.get('header')
            .find('.usa-logo')
            .find('img')
            .should('have.attr', 'src', '/themes/custom/usagov/images/Logo_USAGov.png')
            .should('have.attr', 'alt', 'USAGov Logo')
    })
    it('Link with Contact Center number appears in header area and links to contact page', () => {
        cy.get('header')
            .find('#top-phone')
            .find('a')
            .click()

        // Should be on a new URL which includes '/phone'
        cy.url().should('include', '/phone')
    })
    it('Español toggle appears and links to Spanish homepage', () => {
        cy.get('header')
            .find('.language-link')
            .click()

        // Should be on a new URL which includes '/es'
        cy.url().should('include', '/es')
    })
    it('Search bar appears with search icon in header region; can successfully complete search', () => {
        const typedText = 'housing'

        // Enters query into search input 
        cy.get('header')
            .find('#search-field-small')
            .type(typedText)
            .should('have.value', typedText)
            .type('{enter}')

        // Origin URL should now be search.gov
        const sentArgs = { query: typedText }
        cy.origin(
            'https://search.usa.gov/', 
            { args: sentArgs }, 
            ({ query }) => {
                cy.get('#query').should('have.value', query)
            }
        )

        // Go back to localhost to test search icon
        cy.visit('/')
        cy.get('header')
            .find('#search-field-small')
            .next()
            .find('img')
            .should('have.attr', 'alt', 'Search')

        cy.get('header')
            .find('#search-field-small')
            .next()
            .click()
            
        // Verify URL is search.gov
        cy.origin('https://search.usa.gov/', () => {
            cy.url().should('include', 'search.usa.gov')
        })
    })
    it('Homepage: Main menu appears after header; links work appropriately. All topics link goes down the page.', () => {
        // Main menu appears
        cy.get('.usa-nav__primary')
            .should('be.visible')
        
        // Test All Topics link
        cy.get('#usa-nav__topics')
            .find('a')
            .click()
        
        cy.url().should('include', '#all-topics-header')

        // Test About link
        cy.get('#usa-nav__about')
            .find('a')
            .click()
        
        cy.url().should('include', '/about')

        // Test Benefits link
        cy.visit('/')
        cy.get('#usa-nav_benefits')
            .find('a')
            .click()
        
        cy.url().should('include', '/benefits')

        // Test Housing link
        cy.visit('/')
        cy.get('#usa-nav_housing')
            .find('a')
            .click()
        
        cy.url().should('include', '/housing-help')

        // Test Scams link
        cy.visit('/')
        cy.get('#usa-nav__scams')
            .find('a')
            .click()
        
        cy.url().should('include', '/scams-and-fraud')

        // Test Taxes link
        cy.visit('/')
        cy.get('#usa-nav_taxes')
            .find('a')
            .click()
        
        cy.url().should('include', '/taxes')

        // Test Travel link
        cy.visit('/')
        cy.get('#usa-nav_travel')
            .find('a')
            .click()
        
        cy.url().should('include', '/travel')
    })
    it('Banner area/image appears with Welcome text box', () => {
        cy.get('.banner-div')
            .should('be.visible')
        
        cy.get('.welcome-box')
            .should('be.visible')
    })
})