const paths = ["/disasters-and-emergencies", "/es/desastres-emergencias"];

paths.forEach(path => {
  let lang;
  if (path === "/disasters-and-emergencies") {
    lang = "English";
  } else {
    lang = "Español";
  }
  describe(`${lang} 'Nav Card Page`, () => {
    // Set base URL
    beforeEach(() => {
      cy.visit(path);
    });
    it('BTE 20: Banner image appears on topic pages', () => {
        cy.get('#block-usagov-content')
            .find('.usagov-hero')
            .should('be.visible')
            .should('have.css', 'background-image')
    })
    it('BTE 21: Most popular links appear and function correctly on topic pages', () => {
        cy.get('.usagov-hero__callout')
            .should('be.visible')

        cy.get('.usagov-hero__callout')
            .find('a')
            .each((el) => {
                // Validate link
                cy.wrap(el)
                    .invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
            })
    })
    it('BTE 22: Cards on nav card page appear/function correctly on topic pages', () => {
        cy.get('.usagov-cards')
            .find('li')
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
                cy.wrap(el).find('a')
                    .realHover()
                    .should('have.css', 'background-color', 'rgb(204, 236, 242)')
            })
    })
    it('BTE 23: False children items appear as cards on on topic pages', () => {
        if (lang == "English") {
        let falseChildren = [
            'Financial assistance after a disaster',
            'What to do before and after a natural disaster or emergency',
            'Government benefits'
        ]

        for (let i = 0; i < falseChildren.length; i++) {
            cy.get('.usagov-cards')
                .contains(falseChildren[i])
                .should('be.visible')
            }
        } else {
            let falseChildren = [
                'Asistencia financiera por desastre natural',
                'Qué hacer antes y después de un desastre natural o emergencia',
            ]

            for (let i = 0; i < falseChildren.length; i++) {
                cy.get('.usagov-cards')
                    .contains(falseChildren[i])
                    .should('be.visible')
            }
        }
        })
    })
})
