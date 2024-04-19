describe('Content Page [ENG]', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/disaster-assistance')

        cy.injectAxe()
    })
    it('BTE 28: Left menu appears on page and indicates the page you are on', () => {
        cy.get('.usa-sidenav')
            .should('be.visible')

        // Menu indicates what page you are on
        cy.get('.usa-sidenav')
            .find('.usa-current')
            .invoke('text')
            .then((menuCurrent) => {
              // Grab page title and compare to breadcrumb text
              cy.get('h1')
                .invoke('text')
                .should((pageTitle) => {
                    expect(pageTitle.trim().toLowerCase()).to.include(menuCurrent.trim().toLowerCase())
                })
            })
    })
    it('BTE 29: Breadcrumb appears at top of page and indicates correct section', () => {
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
                    expect(pageTitle.trim().toLowerCase()).to.include(breadcrumb.trim().toLowerCase())
                })
            })
    })
    it('BTE 30: Page titles and headings are formatted correctly', () => {
        // CSS style checks

        // h1
        // font-size: 2.44rem;
        cy.get('h1')
            .then(el => {
                const win = cy.state('window')
                const styles = win.getComputedStyle(el[0])

                const fontFamily = styles.getPropertyValue('font-family')
                expect(fontFamily).to.include('Merriweather Web')
            })
            .should('have.css', 'font-weight', '700')
            .should('have.css', 'color', 'rgb(216, 57, 51)')
        
        // h2
        // font-size: 1.95rem;
        cy.get('h2')
            .not('.usa-card__heading')
            .each((h2) => {
                cy.wrap(h2)
                    .then(el => {
                        const win = cy.state('window')
                        const styles = win.getComputedStyle(el[0])
        
                        const fontFamily = styles.getPropertyValue('font-family')
                        expect(fontFamily).to.include('Merriweather Web')
                    })
                    .should('have.css', 'font-weight', '700')
                    .should('have.css', 'color', 'rgb(27, 27, 27)')
            })

        // h3
        // font-size: 1.34rem;
        cy.get('.content-wrapper')
            .find('h3')
            .each((h3) => {
                cy.wrap(h3)
                    .then(el => {
                        const win = cy.state('window')
                        const styles = win.getComputedStyle(el[0])
        
                        const fontFamily = styles.getPropertyValue('font-family')
                        expect(fontFamily).to.include('Merriweather Web')
                    })
                    .should('have.css', 'font-weight', '700')
                    .should('have.css', 'color', 'rgb(27, 27, 27)')
            })
    })
    it('BTE 31: Español toggle appears on page and takes you to Spanish page', () => {
        cy.get('.language-link')
            .click()

        // Should be on a new URL which includes '/es' and '/solicitar-asistencia-desastre'
        cy.url()
            .should('include', '/es')
            .should('include', '/solicitar-asistencia-desastre')
    })
    it('BTE 32: Last updated date appears at bottom of content with correct padding above it', () => {
        // make sure date appears
        cy.get('.additional_body_info')
            .find('#last-updated')
            .should('exist')
    })
    it('BTE 33: Share this page function works correctly for facebook, twitter, and email', () => {
        // test links for each social
        cy.get('.additional_body_info')
            .find('#sm-share')
            .should('exist')
            .get('div.share-icons>a').eq(0)
            .should('have.attr', 'href', 'http://www.facebook.com/sharer/sharer.php?u=http://localhost/disaster-assistance&v=3')
            .get('div.share-icons>a').eq(1)
            .should('have.attr', 'href', 'http://twitter.com/intent/tweet?source=webclient&text=http://localhost/disaster-assistance')
            .get('div.share-icons>a').eq(2)
            .should('have.attr', 'href', 'mailto:?subject=http://localhost/disaster-assistance')
    })
    it('BTE 34: Do you have a question block appears at bottom of content page with icons and links to phone and chat', () => {
        // test question box
        cy.get('.additional_body_info')
            .find('#question-box')
            .should('exist')
            .find('a')
            .should('have.attr', 'href', '/phone')
    })
    it('BTE 35: Page level survey appears and you can complete survey', () => {
        cy.get('.pagesurvey-qual-container')
            //select in first page of survey
            .should('exist')
            .get('ul.ChoiceStructure')
            .find('li').first().click()
            .get('#Buttons')
            .find('input').click()
            //select in second page of survey
            .get('ul.ChoiceStructure')
            .find('li').first().click()
            .get('#Buttons')
            .find('input').click()
            //fill in third page of survey
            .get('div.ChoiceStructure')
            .find('textarea').type('test')
            .get('#Buttons')
            .find('input').click()
            .get('#EndOfSurvey').should('exist')
    })

    it('BTE 36: Back to top button', () => {
        //test back to top button
        cy.scrollTo('bottom')
            .get('#back-to-top')
            .click()
            .url()
            .should('include', '#main-content')
    })
    

})