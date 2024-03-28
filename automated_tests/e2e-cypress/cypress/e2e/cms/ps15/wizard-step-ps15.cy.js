describe('Local cms login', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a federal directory page
    cy.get('ul > li > a').contains('Wizard Step').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Wizard Step Test Title")

    //Selects Language
    cy.get('#edit-langcode-0-value option:selected').should('have.value', 'en')
    //cy.get("#edit-langcode-0-value").select("Español")

    //meta description
    cy.get("#edit-field-meta-description-0-value").type("This is a test wizard step meta description")


    //Put content in the Body

    cy.get("iframe").first()
          .its('0.contentDocument')
          .its('body')
          .find('p')
          .type('Did the scam mention:')
          .type('{selectAll}')
    cy.get('#cke_13').click()


    //wizard step
    cy.get('#edit-field-wizard-step-0-target-id').type('Federal tax or IRS scam (65)')

    //add another item button
    cy.get('#edit-field-wizard-step-add-more').click()
    cy.get('[data-drupal-selector="edit-field-wizard-step-1-target-id"]').type('Social Security scam (66)')

    //add another item button
    cy.get('[data-drupal-selector="edit-field-wizard-step-add-more"]').click()
    cy.get('[data-drupal-selector="edit-field-wizard-step-2-target-id"]').type('Other imposter scams (67)')

    //option name
    //cy.get('#edit-field-option-name-0-value').type()


    //header html text area
    //cy.get('#edit-field-header-html-0-value').type('<script type="text/javascript" src="//script.crazyegg.com/pages/scripts/0007/9651.js" async="async" ></script>')

    //Save page
    //cy.get('[ data-drupal-selector="edit-submit" ]').click()

    //cy.screenshot('wizardStep')

    //delete test page
    /*
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('Wizard Step Test Title')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-submit--2').click()
    cy.get('#edit-submit').click()
    */

    Cypress.Screenshot.defaults({
      capture: 'viewport',
    })

  })
})
