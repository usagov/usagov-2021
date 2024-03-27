describe('Local cms login', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    //cy.get('div > a#toolbar-item-administration')
    //cy.get('ul.toolbar-menu:first > li.menu-item:nth-of-type(2) > a ~ ul.toolbar-menu:first > li.menu-item:first > a ~ ul.toolbar-menu:first > li.menu-item:first > a').focus().click()
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("This is a test title Espanol")
    cy.get("#edit-field-page-intro-0-value").type("This is a test page intro Espanol")
    cy.get("#edit-field-meta-description-0-value").type("This is a test meta description Espanol")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description Espanol")

    //cy.get("edit-field-language-toggle-0-target-id").type()

    //Select page type
    cy.get("#edit-field-page-type").select("Standard Page")
    //cy.get("#edit-field-page-type").select("Life Events")
    //cy.get("#edit-field-page-type").select("State Office Page")
    //cy.get("#edit-field-page-type").select("Life Events Landing Page")
    //cy.get("#edit-field-page-type").select("Navigation Cards Page")
    //cy.get("#edit-field-page-type").select("Navigation Page")
    //cy.get("#edit-field-page-type").select("Standard Page- Nav Hidden")

    //Selects Language
    //cy.get('#edit-langcode-0-value option:selected').select('Egnlish').should('have.value', 'English')
    cy.get("#edit-langcode-0-value").select("Español")

    //add content to the wysiwyg
    cy.get('div.ck-editor__main .ck-blurred').eq(0).click()
    cy.get('div.ck-editor__main .ck-focused').eq(0)
    cy.get('.ck-content[contenteditable=true]').realType('Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia.')

    //Select image
    //cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]')
    //cy.get('[data-drupal-selector="edit-upload-upload-yodljhjblcy"]').selectFile('Banner_img_Birth_en.png')

    //add link to left menu
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/testing/test1')

    //Select how to Saves Page
    //Right now I can't publish duo to the software not having rights to publish
    //Right now software cna only save as Draft or Ready for Review
    cy.get("#edit-moderation-state-0-state").select("Draft")
    //cy.get("#edit-moderation-state-0-state").select("Publish")

    //Save page
    cy.get('[ data-drupal-selector="edit-submit" ]').click()

    //delete test page
    /*
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('This is a test title Espanol')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-submit--2').click()
    cy.get('#edit-submit').click()
    */
  })
})
