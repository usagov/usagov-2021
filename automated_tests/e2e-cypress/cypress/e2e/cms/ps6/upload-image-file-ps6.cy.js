describe('Local cms login', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    //cy.get('div > a#toolbar-item-administration')
    //cy.get('ul.toolbar-menu:first > li.menu-item:nth-of-type(2) > a ~ ul.toolbar-menu:first > li.menu-item:first > a ~ ul.toolbar-menu:first > li.menu-item:first > a').focus().click()
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("File Upload test")
    cy.get("#edit-field-page-intro-0-value").type("Find government programs to help during pregnancy and early childhood. test")
    cy.get("#edit-field-meta-description-0-value").type("Find government programs for food, health care, and other expenses to help during pregnancy and early childhood. See how to collect child support. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")
    //cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')
    //cy.get('#ui-id-4').click()

    //Selects Language
    //cy.get('#edit-langcode-0-value option:selected').select('Egnlish').should('have.value', 'English')
    //cy.get("#edit-langcode-0-value").select("Español")

    //Input page for the toggle language content
    //cy.get("edit-field-language-toggle-0-target-id").type()

    //Select html for wysywig to put html code
    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

    //#cke_1_top, #cke_1_toolbox, #cke_42
    cy.get('#cke_42').click()

   //Put content in the Body
   cy.get("iframe").first()
   .its('0.contentDocument')
   .its('body')
   .find('p')
   .type('This is a test to upload a file image.')


    //Select page type
    //cy.get("#edit-field-page-type").select("Standard Page")
    cy.get("#edit-field-page-type").select("Life Events")
    //cy.get("#edit-field-page-type").select("State Office Page")
    //cy.get("#edit-field-page-type").select("Life Events Landing Page")
    //cy.get("#edit-field-page-type").select("Navigation Cards Page")
    //cy.get("#edit-field-page-type").select("Navigation Page")
    //cy.get("#edit-field-page-type").select("Standard Page- Nav Hidden")

    //Select image
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]').click()
    //cy.get('#drupal-modal > #media-library-wrapper > #media-library-content > #media-library-add-form-wrapper').should('be.visible')
    cy.get('.media-library-widget-modal').should('be.visible')
    cy.get('#media-library-add-form-wrapper').should('be.visible')
    cy.get('div.form-managed-file__main > input:first').click().selectFile('Banner_img_Birth_en.png')
    cy.get('#drupal-modal').should('be.visible')
    cy.get('.form-managed-file__meta-wrapper').should('be.visible')
    cy.get('.form-item--media-0-fields-field-media-image-0-alt > input').type('baby in arm')
    cy.get('button').contains('Save and insert').click()


    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/testing/test23')

    //Select how to Saves Page
    //Right now I can't publish due to the software not having rights to publish
    //Right now software cna only save as Draft or Ready for Review
    cy.get('.layout-region__content')
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-selection-0-rendered-entity"]').should('be.visible')
    cy.get("#edit-moderation-state-0-state").select("Draft")
    //cy.get("#edit-moderation-state-0-state").select("Publish")

    //Save page
    cy.get('[ data-drupal-selector="edit-submit" ]').click()
    cy.screenshot('imageUpload')

     //delete test page

    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('File Upload test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-submit--2').click()
    cy.get('#edit-submit').click()


  })
})
