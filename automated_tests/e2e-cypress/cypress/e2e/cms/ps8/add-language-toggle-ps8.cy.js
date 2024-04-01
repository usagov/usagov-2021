describe('Local cms login', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("File Upload test")
    cy.get("#edit-field-page-intro-0-value").type("Find government programs to help during pregnancy and early childhood. test")
    cy.get("#edit-field-meta-description-0-value").type("Find government programs for food, health care, and other expenses to help during pregnancy and early childhood. See how to collect child support. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select page type
    cy.pageType()

    //Input page for the toggle language content
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')

    //add English text to wysiwyg
    cy.textEnglish()

    //Select image
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]').click()
    cy.get('.media-library-widget-modal').should('be.visible')
    cy.get('#media-library-view')
    cy.get('.view-content')
    cy.get('.views-form')
    cy.get('.media-library-views-form__rows')
    cy.get('.views-field-media-library-select-form')
    cy.get('.field-content').eq(0)
    cy.get('.form-item--media-library-select-form-0').eq(0)
    cy.get('[data-drupal-selector="edit-media-library-select-form-0"]').check()
    cy.get('.ui-dialog-buttonset>.media-library-select').click()


    // cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]').click()
    // cy.get('#drupal-modal > #media-library-wrapper > #media-library-content > #media-library-add-form-wrapper').should('be.visible')
    // cy.get('.media-library-widget-modal').should('be.visible')
    // cy.get('#media-library-add-form-wrapper').should('be.visible')
    // cy.get('div.form-managed-file__main > input:first').click().selectFile('Banner_img_Birth_en.png')
    // cy.get('#drupal-modal').should('be.visible')
    // cy.get('.form-managed-file__meta-wrapper').should('be.visible')
    // cy.get('.form-item--media-0-fields-field-media-image-0-alt > input').type('baby in arm')
    // cy.get('button').contains('Save and insert').click()


    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/testing/test23')

    //Select how to Saves Page
    //Right now I can't publish due to the software not having rights to publish
    //Right now software cna only save as Draft or Ready for Review
    cy.get('.layout-region__content')
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-selection-0-rendered-entity"]').should('be.visible')
    cy.get("#edit-moderation-state-0-state").select("Draft")
    //cy.get("#edit-moderation-state-0-state").select("Publish")

    //publish page
    cy.pagePublish()

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('File Upload test')
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
    cy.get('table > thead ~ tbody > tr > td > a').contains('File Upload test').click()
    */
  })
})
