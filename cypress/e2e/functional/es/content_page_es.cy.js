describe('Content Page', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/es/bibliotecas-publicas')

        cy.injectAxe()
    })
    it('Has no critical impact accessibility violations on load', () => {
        // Test on initial load, only report and assert for critical impact items
        cy.checkA11y(null, {
          includedImpacts: ['critical']
        })
    })
    it('BTS 28: Left menu appears on page and indicates the page you are on', () => {
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
    it('BTS 29: Breadcrumb appears at top of page and indicates correct section', () => {
        cy.get('.usa-breadcrumb__list')
            .find('li')
            .first()
            .contains('Página principal')

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
    it('BTS 30: Page titles and headings are formatted correctly', () => {
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
    })
    it('BTS 31: English toggle appears on page and takes you to English page', () => {
        cy.get('.language-link')
            .click()

        // Should be on a new URL which includes '/es' and '/solicitar-asistencia-desastre'
        cy.url()
            .should('not.include', '/es')
            .should('include', '/libraries')
    })
})