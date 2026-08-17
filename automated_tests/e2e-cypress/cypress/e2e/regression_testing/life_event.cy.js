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
            .click();
        cy.location('pathname').should('eq', '/financial-hardship');
        cy.visit(path);

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/early-childhood');
        cy.visit(path);

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/adulthood');
        cy.visit(path);

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/approaching-retirement');
        cy.visit(path);

        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/disaster');
        cy.visit(path);

        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/death-loved-one');
        cy.visit(path);

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
            .click();
        cy.location('pathname').should('eq', '/es/enfrentar-dificultades-economicas');
        cy.visit(path);

        // test second card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(1)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/es/embarazo-primera-infancia');
        cy.visit(path);

        // test third card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(2)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/es/prepararse-para-la-jubilacion');
        cy.visit(path);

        // test fourth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(3)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/es/transicion-edad-adulta');
        cy.visit(path);

        // test fifth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(4)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/es/recuperarse-desastre-natural');
        cy.visit(path);

        // test sixth card
        cy.get('ul.usa-card-group')
            .find('li')
            .eq(5)
            .find('a')
            .click();
        cy.location('pathname').should('eq', '/es/muerte-de-un-ser-querido');
        }
        })
    })

})
