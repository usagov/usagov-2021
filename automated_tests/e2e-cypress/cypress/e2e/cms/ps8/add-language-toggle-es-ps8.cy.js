describe('Add a language toggle to a Spanish page', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("File Upload Spanish test")
    cy.get("#edit-field-page-intro-0-value").type("Find government programs to help during pregnancy and early childhood. test")
    cy.get("#edit-field-meta-description-0-value").type("Find government programs for food, health care, and other expenses to help during pregnancy and early childhood. See how to collect child support. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select page type
    cy.pageType()

    //Select Spanish language
    cy.languageToggle()

    //Input for the language toggle page
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Having a child and early childhood')

    //Select html for wysywig to put html code
    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

    //add Spanish text to wysiwyg
    cy.textSpanish()

    //Select navigation page image
    cy.imageSelect()

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/testing/test23')

    //publish page
    cy.pagePublish()

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('File Upload Spanish test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()

    //Use this code to add toggle to an existing page
    /*
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('File Upload test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('div.dropbutton-widget > ul > li > a').contains('Edit').click()
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')
    cy.get('#ui-id-4').click()
    cy.get("#edit-moderation-state-0-state").select("Draft")
    cy.get('#edit-submit').click()
    cy.get('table > thead ~ tbody > tr > td > a').contains('File Upload Spanish test').click()
    */

  })
})
