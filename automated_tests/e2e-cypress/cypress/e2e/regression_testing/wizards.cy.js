const paths = ["/scams-and-fraud", "/es/estafas-y-fraudes"];

paths.forEach(path => {
  let lang;
  if (path === "/elected-officials") {
    lang = "English";
  } else {
    lang = "Español";
  }
  describe(`Check ${lang} Wizard Breadcrumbs`, () => {
    // Set base URL
    beforeEach(() => {
      cy.visit(path);
    });
    it('Compare breadcrumbs across levels', () => {

        // Get the breadcrumbs on this parenting pages to compare against
        cy.get('.usa-breadcrumb__list-item').first().invoke('text').then((text) => {
          const cleanedText = text
            .replace(/\s+/g, ' ')   // Replace multiple spaces & new lines with a single space
            .replace(/\u00a0/g, ' ') // Replace non-breaking spaces (&nbsp;)
            .trim();                 // Trim leading/trailing spaces
          cy.wrap(cleanedText).as('breadFirst');
          cy.log('Setting expectation for the 1st breadcrumb to be: ' + cleanedText);
        });
        cy.get('.usa-breadcrumb__list-item').eq(1).invoke('text').then((text) => {
          const cleanedText = text
            .replace(/\s+/g, ' ')   // Replace multiple spaces & new lines with a single space
            .replace(/\u00a0/g, ' ') // Replace non-breaking spaces (&nbsp;)
            .trim();                 // Trim leading/trailing spaces
          cy.wrap(cleanedText).as('breadSecond');
          cy.log('Setting expectation for the 2nd breadcrumb to be: ' + cleanedText);
        });

        // CLick to go to the to-level of the wizard
        cy.get('.usa-card-group > li > a').first().click()

        // Confirm the 1st 2 breadcrumbs are the same as the parent
        cy.get('@breadFirst').then((breadFirst) => {
          cy.get('.usa-breadcrumb__list-item').first().invoke('text').should('contain', breadFirst);
        });
        cy.get('@breadSecond').then((breadSecond) => {
          cy.get('.usa-breadcrumb__list-item').eq(1).invoke('text').should('contain', breadSecond);
        });

        // Click to navigate one level down within the Wizard
        cy.get('a.usa-button--big').click()

        // Confirm the 1st 2 breadcrumbs are the same as the grandparent
        cy.get('@breadFirst').then((breadFirst) => {
          cy.get('.usa-breadcrumb__list-item').first().invoke('text').should('contain', breadFirst);
        });
        cy.get('@breadSecond').then((breadSecond) => {
          cy.get('.usa-breadcrumb__list-item').eq(1).invoke('text').should('contain', breadSecond);
        });

        // Tick the first radio-button, and then click to navigate one more level down in the Wizard
        cy.get('.views-field-field-option-name input').first().parent().click()
        cy.get('button.usa-button--big').last().click()

        // Confirm the 1st 2 breadcrumbs are the same as the great-grandparent
        cy.get('@breadFirst').then((breadFirst) => {
          cy.get('.usa-breadcrumb__list-item').first().invoke('text').should('contain', breadFirst);
        });
        cy.get('@breadSecond').then((breadSecond) => {
          cy.get('.usa-breadcrumb__list-item').eq(1).invoke('text').should('contain', breadSecond);
        });

        })
    })
})
