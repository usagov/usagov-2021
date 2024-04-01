describe('Local cms login', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Embarazo y primera infancia test")
    cy.get("#edit-field-page-intro-0-value").type("Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia. test")
    cy.get("#edit-field-meta-description-0-value").type("Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select page type
    cy.pageType()

    //Select Spanish language
    cy.languageToggle()

    //Input for the language toggle page
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Having a child and early childhood')

    //select html option for wysiwyg
    //cy.get('#edit-body-0-format--2').click().select("HTML")

    //add Spanish text to wysiwyg
    cy.textSpanish()

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

    //add link to menu and select taxonomy
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()
    cy.get('[data-drupal-selector="edit-menu-title"]').clear().type('Embarazo y primera infancia')
    //cy.get('#edit-menu-node-menus-en-menu-parent').select('-- Life events')
    cy.get('#edit-menu-node-menus-es-menu-parent').select('-- Etapas importantes de la vida')

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/es/embarazo-primera-infancia-test')

    //publish page
    cy.pagePublish()

    //Takes screenshot of the page
    //cy.screenshot('createBasicPage')

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('Embarazo y primera infancia test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
