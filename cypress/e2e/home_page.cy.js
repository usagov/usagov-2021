describe('Home Page', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/')
    })
    /*it.only('All links are valid', () => {
        cy.get('a')
            .filter(':visible')
            .not('.usa-sr-only')
            .each((link) => {
                cy.visit(link.attr('href'));
                cy.contains('Page not found').should('not.exist')

                cy.go('back')
            })
    })*/
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
        cy.go('back')
        cy.get('#usa-nav_benefits')
            .find('a')
            .click()
        
        cy.url().should('include', '/benefits')

        // Test Housing link
        cy.go('back')
        cy.get('#usa-nav_housing')
            .find('a')
            .click()
        
        cy.url().should('include', '/housing-help')

        // Test Scams link
        cy.go('back')
        cy.get('#usa-nav__scams')
            .find('a')
            .click()
        
        cy.url().should('include', '/scams-and-fraud')

        // Test Taxes link
        cy.go('back')
        cy.get('#usa-nav_taxes')
            .find('a')
            .click()
        
        cy.url().should('include', '/taxes')

        // Test Travel link
        cy.go('back')
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
    it('How do I area appears correctly with links to four pages/topics', () => {
        cy.get('.how-box')
            .contains('How do I')
            .should('be.visible')
        
        // Verify there are 4 links
        cy.get('.how-box')
            .find('a')
            .as('links')
            .should('be.visible')
            .should('have.length', 4)

        // Check each link is valid
        cy.get('@links')
            .each((link) => {
                cy.visit(link.attr('href'));
                cy.contains('Page not found').should('not.exist')

                cy.go('back')
            })
    })
    it('Jump to All topics and services link/button appears and jumps to correct place on page', () => {
        // Check text and button
        cy.get('.jump')
            .contains('Jump to')
            .should('be.visible')
            
        cy.get('.jump')
            .find('img')
            .should('be.visible')
            .should('have.attr', 'src', '/themes/custom/usagov/images/Reimagined_Jump_to_Arrow.svg')
            .should('have.attr', 'alt', 'Jump to all topics and services')
        
        // Verify link is valid
        cy.get('.jump')
            .each((el) => {
                cy.visit(el.find('a').attr('href'))
                cy.url().should('include', '#all-topics-header')

                cy.visit('/')
            })
    })
})