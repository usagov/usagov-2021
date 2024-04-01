describe('Add a navigation image to Spanish page', () => {
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

    //add Spanish text to wysiwyg
    cy.textSpanish()

    //Select navigation page image
    cy.imageSelect()

    //checkbox to generate an automatic page url alias
    cy.get('#edit-path-0-pathauto').check()

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
  })
})
