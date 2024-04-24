describe('Local cms login', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a federal directory page
    cy.get('ul > li > a').contains('Wizard').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Wizard Test Title")

    //Selects Language
    cy.get('#edit-langcode-0-value option:selected').should('have.value', 'en')
    //cy.get("#edit-langcode-0-value").select("Español")

    //meta description
    cy.get("#edit-field-meta-description-0-value").type("This is a test wizard meta description")


    //Put content in the Body
    /*
    cy.get("iframe").first()
          .its('0.contentDocument')
          .its('body')
          .find('p')
          .type('An official website of the U.S. General Services Administration')
    */

    //wizard step
    cy.get('#edit-field-wizard-step-0-target-id').type('What type of scam do you need to report? (822)')

    //cy.get("edit-field-language-toggle-0-target-id").type()

    //header html text area
    cy.get('#edit-field-header-html-0-value').type('<script type="text/javascript" src="//script.crazyegg.com/pages/scripts/0007/9651.js" async="async" ></script>')

    //fill out url alias
    //cy.get('#edit-path-0').click()
    //cy.get('#edit-path-0-alias').type('/where-report-scams-test')

    //Save page
    cy.get('[ data-drupal-selector="edit-submit" ]').click()

    cy.screenshot('wizard')

    //delete test page
    /*
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('Wizard Test Title')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-submit--2').click()
    cy.get('#edit-submit').click()
    */

  })
})
