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
            .url().should('include', '/financial-hardship')
            .go('back')

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/early-childhood')
            .go('back')

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/adulthood')
            .go('back')

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/approaching-retirement')
            .go('back')

        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/disaster')
            .go('back')

        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/death-loved-one')
            .go('back')

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
            .url().should('include', '/es/enfrentar-dificultades-economicas')
            .go('back')

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click()
            .url().should('include', '/es/embarazo-primera-infancia')
            .go('back')

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click()
            .url().should('include', '/es/prepararse-para-la-jubilacion')
            .go('back')

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click()
            .url().should('include', '/es/transicion-edad-adulta')
            .go('back')

        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click()
            .url().should('include', '/es/recuperarse-desastre-natural')
            .go('back')

        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click()
            .url().should('include', '/es/muerte-de-un-ser-querido')
            .go('back')
        }
        })
    })

})