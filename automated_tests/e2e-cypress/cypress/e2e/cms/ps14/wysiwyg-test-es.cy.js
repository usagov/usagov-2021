describe('Local cms login', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("This is a test title Spanish")
    cy.get("#edit-field-page-intro-0-value").type("This is a test page intro Spanish")
    cy.get("#edit-field-meta-description-0-value").type("This is a test meta description Spanish")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description Spanish")

    //Select Spanish language
    cy.languageToggle()

    //add content to the wysiwyg
    cy.get('div.ck-editor__main .ck-blurred').eq(0).click()
    cy.get('div.ck-editor__main .ck-focused').eq(0)
    cy.get('.ck-content[contenteditable=true]').realType('Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia.')

    //Select image
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]').click()
    cy.get('.media-library-widget-modal').should('be.visible')
    cy.get('.views-form')
    cy.get('[data-drupal-selector="edit-media-library-select-form-5"]').check()
    cy.get('.ui-dialog-buttonset > button').click()
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-selection-0-rendered-entity"]').should('be.visible')
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()
    //cy.get('[data-drupal-selector="edit-menu-title"]').type('This is a test title b')
    cy.get('#edit-menu-node-menus-es-menu-parent').select('-- Etapas importantes de la vida')

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/test-title-29')


    //Select how to Saves Page
    //Right now I can't publish duo to the software not having rights to publish
    //Right now software cna only save as Draft or Ready for Review
    cy.get("#edit-moderation-state-0-state").select("Draft")
    //cy.get("#edit-moderation-state-0-state").select("Publish")

    //Save page
    cy.get('[ data-drupal-selector="edit-submit" ]').click()

    //Take screenshot
    //cy.screenshot()

    //publish page
    cy.get('#content-moderation-entity-moderation-form')
    cy.get('#edit-new-state').select('Published')
    cy.get('#edit-submit').click()

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('This is a test title Spanish')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
