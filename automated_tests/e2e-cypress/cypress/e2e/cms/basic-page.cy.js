describe('Create a basic page', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("This is a test title")
    cy.get("#edit-field-page-intro-0-value").type("This is a test page intro")
    cy.get("#edit-field-meta-description-0-value").type("This is a test meta description")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select html for wysywig to put html code
    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

    //add English text to wysiwyg
    cy.textEnglish()

    //Select image
    //cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]')
    //cy.get('[data-drupal-selector="edit-upload-upload-yodljhjblcy"]').selectFile('Banner_img_Birth_en.png')

    //add link to left menu
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()

    //checkbox to generate an automatic page url alias
    cy.get('#edit-path-0-pathauto').check()

    //publish page
    cy.pagePublish()

    //delete test page
    // cy.get('ul > li > a').contains('Content').focus().click()
    // cy.get('#edit-combine').type('This is a test title')
    // cy.get('#edit-submit-content').click()
    // cy.get('#edit-node-bulk-form-0').check()
    // cy.get('#edit-action').select('Delete content')
    // cy.get('#edit-submit').click()
    // cy.get('#edit-submit').click()
  })
})
