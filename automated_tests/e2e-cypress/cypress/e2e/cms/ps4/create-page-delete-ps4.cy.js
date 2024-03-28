describe('Local cms login', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Get food assistance with the WIC program test")
    cy.get("#edit-field-page-intro-0-value").type("The Special Supplemental Nutrition Program for Women, Infants, and Children (WIC) can help you and your young children get food, nutrition counseling, and social service referrals. test")
    cy.get("#edit-field-meta-description-0-value").type("The Special Supplemental Nutrition Program for Women, Infants, and Children (WIC) can help you and your young children get food, nutrition counseling, and social service referrals. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select page type
    //cy.get("#edit-field-page-type").select("Standard Page")
    cy.get("#edit-field-page-type").select("Life Events")
    //cy.get("#edit-field-page-type").select("State Office Page")
    //cy.get("#edit-field-page-type").select("Life Events Landing Page")
    //cy.get("#edit-field-page-type").select("Navigation Cards Page")
    //cy.get("#edit-field-page-type").select("Navigation Page")
    //cy.get("#edit-field-page-type").select("Standard Page- Nav Hidden")

    //Language toggle to Spanish page
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Obtenga asistencia alimentaria con el programa WIC')

    //select html option for wysiwyg
    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

    //add content to the wysiwyg
    cy.get('div.ck-editor__main .ck-blurred').eq(0).click()
    cy.get('div.ck-editor__main .ck-focused').eq(0)
    cy.get('.ck-content[contenteditable=true]').realType('The Special Supplemental Nutrition Program for Women, Infants, and Children (WIC) can help you and your young children get food, nutrition counseling, and social service referrals.')

    //Select image
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]').click()
    //cy.get('#drupal-modal > #media-library-wrapper > #media-library-content > #media-library-add-form-wrapper').should('be.visible')
    cy.get('.media-library-widget-modal').should('be.visible')
    cy.get('.views-form')
    //cy.get('[data-drupal-selector="views-form-media-library-widget-image-nkezeyw9ghg"]').focus()
    cy.get('[data-drupal-selector="edit-media-library-select-form-5"]').check()
    cy.get('.ui-dialog-buttonset > button').click()
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-selection-0-rendered-entity"]').should('be.visible')
    //cy.get("input").focus()
    //cy.get('#edit-upload--s6nLDVOayCI > div.form-managed-file__main > #edit-upload-upload--fIl5AIpXUcA').click()

    //.selectFile('Banner_img_Birth_en.png')
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()
    cy.get('[data-drupal-selector="edit-menu-title"]').clear().type('Food assistance with the WIC program')
    cy.get('#edit-menu-node-menus-en-menu-parent').select('-- Life events')

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/food-assistance-test')

    //Select how to Saves Page
    //Right now I can't publish due to the software not having rights to publish
    //Right now software cna only save as Draft or Ready for Review
    cy.get('.layout-region__content')
    cy.get("#edit-moderation-state-0-state").select("Draft")
    //cy.get("#edit-moderation-state-0-state").select("Publish")

    //Save page
    cy.get('[ data-drupal-selector="edit-submit" ]').click()


    //publish page
    cy.get('#content-moderation-entity-moderation-form')
    cy.get('#edit-new-state').select('Published')
    cy.get('#edit-submit').click()

    //Takes screenshot of the page
    //cy.screenshot('createBasicPage')

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('Get food assistance with the WIC program test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
