const paths = ["/life-events", "/es/etapas-importantes-de-la-vida"];

paths.forEach(path => {
  let lang;
  if (path === "/life-events") {
    lang = "English";
  } else {
    lang = "Español";
  }
  describe(`${lang} Life Events`, () => {
    // Set base URL
    beforeEach(() => {
      cy.visit(path);
    });
    it('BTE 53: Landing page looks correct with banner image, imge cards', () => {
        // test banner
        cy.get('#block-usagov-content')
            .find('section')
            .should('be.visible')
        // test image cards
        cy.get('div.usagov-cards')
            .find('ul')
            .should('be.visible')
    })
    it('BTE 53: Landing page link to go to topic pages', () => {
        if (lang == "English") {
        // test number of cards
        cy.get('ul.usa-card-group')
            .find('li')
            .should('have.length', 6)

        // test first card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
        cy.url().should('include', '/financial-hardship')
        cy.go('back')

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
        cy.url().should('include', '/early-childhood')
        cy.go('back')

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
        cy.url().should('include', '/adulthood')
        cy.go('back')

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
        cy.url().should('include', '/approaching-retirement')
        cy.go('back')

        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
        cy.url().should('include', '/disaster')
        cy.go('back')

        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
        cy.url().should('include', '/death-loved-one')
        cy.go('back')

         // test number of cards
         cy.get('ul.usa-card-group')
         .find('li')
         .should('have.length', 6)
        } else {

        // Spanish
        // test first card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(0)
            .find('a')
            .click()
        cy.url().should('include', '/es/enfrentar-dificultades-economicas')
        cy.go('back')

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
        cy.url().should('include', '/es/embarazo-primera-infancia')
        cy.go('back')

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
        cy.url().should('include', '/es/prepararse-para-la-jubilacion')
        cy.go('back')

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
        cy.url().should('include', '/es/transicion-edad-adulta')
        cy.go('back')

        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
        cy.url().should('include', '/es/recuperarse-desastre-natural')
        cy.go('back')

        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
        cy.url().should('include', '/es/muerte-de-un-ser-querido')
        cy.go('back')
        }
        })
    })

})