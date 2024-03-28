describe('Life Event En', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Having a child and early childhood test")
    cy.get("#edit-field-page-intro-0-value").type("Find government programs to help during pregnancy and early childhood. test")
    cy.get("#edit-field-meta-description-0-value").type("Find government programs for food, health care, and other expenses to help during pregnancy and early childhood. See how to collect child support. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select page type
    cy.pageType()

    //Input for the language toggle page
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')

    //add content to the wysiwyg
    cy.get('div.ck-editor__main .ck-blurred').eq(0).click()
    cy.get('div.ck-editor__main .ck-focused').eq(0)
    cy.get('.ck-content[contenteditable=true]').realType('Learn how to get nutritious food for yourself and your family through SNAP (food stamps), D-SNAP, and WIC for women, infants, and children.')

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
    cy.get('[data-drupal-selector="edit-menu-title"]').type('Having a child and early childhood')
    cy.get('#edit-menu-node-menus-en-menu-parent').select('-- Life events')

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/Having-child-early-childhood-test')

    //publish page
    cy.pagePublish()

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('Having a child and early childhood test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
