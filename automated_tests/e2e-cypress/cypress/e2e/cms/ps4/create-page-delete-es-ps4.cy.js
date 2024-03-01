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
    cy.get("#edit-title-0-value").type("Embarazo y primera infancia test")
    cy.get("#edit-field-page-intro-0-value").type("Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia. test")
    cy.get("#edit-field-meta-description-0-value").type("Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')
    cy.get('#ui-id-4').click()
    //Selects Language
    //cy.get('#edit-langcode-0-value option:selected').select('Egnlish').should('have.value', 'English')
    //cy.get("#edit-langcode-0-value").select("Español")

    //cy.get("edit-field-language-toggle-0-target-id").type()


    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

    //#cke_1_top, #cke_1_toolbox, #cke_42
    cy.get('#cke_42').click()

    //Put content in the Body
    cy.get("#cke_1_contents>textarea")
        //.find('textarea')
    //cy.get('[data-drupal-selector="edit-body-0-value"]')
          //.its('0.contentDocument')
          //.its('body')
          //.find('p')
          //.type('Learn how to get nutritious food for yourself and your family through SNAP (food stamps), D-SNAP, and WIC for women, infants, and children.')
          //.type('<p>hello world</p>')
    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

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
    cy.get('[data-drupal-selector="edit-menu-title"]').type('Embarazo y primera infancia')
    cy.get('#edit-menu-node-menus-en-menu-parent').select('-- Life events')

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/es/embarazo-primera-infancia-test')

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
    /*
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('Embarazo y primera infancia test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-submit--2').click()
    cy.get('#edit-submit').click()
    */
  })
})
