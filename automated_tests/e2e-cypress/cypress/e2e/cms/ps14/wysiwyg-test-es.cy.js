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
    cy.get("#edit-title-0-value").type("This is a test title Spanish")
    cy.get("#edit-field-page-intro-0-value").type("This is a test page intro Spanish")
    cy.get("#edit-field-meta-description-0-value").type("This is a test meta description Spanish")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description Spanish")

    //Select Spanish language
    cy.languageToggle()

    //cy.get("edit-field-language-toggle-0-target-id").type()

    //Put content in the Body
    /*
    cy.get("iframe").first()
          .its('0.contentDocument')
          .its('body')
          .find('p')
          .type('Learn how to get nutritious food for yourself and your family through SNAP (food stamps), D-SNAP, and WIC for women, infants, and children.')
          .type('{enter}')
          .type('{selectAll}')
    cy.get('#cke_17').click()
    //cy.get('#cke_1_toolbox')
    cy.get("iframe").first()
          .its('0.contentDocument')
          .its('body')
          .find('p').last()
                  //.type('{moveToEnd}')
                  //.type('{enter}')
                  //.type('{enter}')
                  //.type('{end}')
          .type('hello there')
          .type('{enter}')
          .type('{selectAll}')
    cy.get('#cke_18').click()

    cy.get("iframe").first()
          .its('0.contentDocument')
          .its('body')
          .find('p').last()
          .type('hello out there')
          .type('{enter}')
          .type('{selectAll}')
    cy.get('#cke_19').click()
      */

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
    /*
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-title').type('This is a test title Spanish')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-submit--2').click()
    cy.get('#edit-submit').click()
    */
  })
})
