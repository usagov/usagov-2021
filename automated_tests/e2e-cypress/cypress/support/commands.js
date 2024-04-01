// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

//import 'cypress-axe'

Cypress.Commands.add('logIn', () => {
    cy.visit('user/login')
    cy.get('[data-drupal-selector="edit-name"]').type(Cypress.env('CMS_USER'))
    cy.get('[data-drupal-selector="edit-pass"]').type(Cypress.env('CMS_PASS'))
    cy.get('[data-drupal-selector="edit-submit"]').click()
});

Cypress.Commands.add('logOut', () => {
    cy.get('#toolbar-item-user').click()
    cy.get('#toolbar-item-user-tray').contains('Log out').click()
});

Cypress.Commands.add('pageType', () => {
    //Select page type
    //cy.get("#edit-field-page-type").select("Standard Page")
    cy.get("#edit-field-page-type").select("Life Events")
    //cy.get("#edit-field-page-type").select("State Office Page")
    //cy.get("#edit-field-page-type").select("Life Events Landing Page")
    //cy.get("#edit-field-page-type").select("Navigation Cards Page")
    //cy.get("#edit-field-page-type").select("Navigation Page")
    //cy.get("#edit-field-page-type").select("Standard Page- Nav Hidden")
});

Cypress.Commands.add('languageToggle', () => {
    //Selects Language
    //cy.get('#edit-langcode-0-value option:selected').select('Egnlish').should('have.value', 'English')
    cy.get("#edit-langcode-0-value").select("Español")
});

Cypress.Commands.add('textEnglish', () => {
    //add content to the wysiwyg
    cy.get('div.ck-editor__main .ck-blurred').eq(0).click()
    cy.get('div.ck-editor__main .ck-focused').eq(0)
    cy.get('.ck-content[contenteditable=true]').realType('The Special Supplemental Nutrition Program for Women, Infants, and Children (WIC) can help you and your young children get food, nutrition counseling, and social service referrals.')
});

Cypress.Commands.add('textSpanish', () => {
    //add content to the wysiwyg
    cy.get('div.ck-editor__main .ck-blurred').eq(0).click()
    cy.get('div.ck-editor__main .ck-focused').eq(0)
    cy.get('.ck-content[contenteditable=true]').realType('Encuentre programas del Gobierno que ofrecen ayuda durante el embarazo y la primera infancia.')
});

Cypress.Commands.add('imageSelect', () => {
    //add navigation page image
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-open-button"]').click()
    cy.get('.media-library-widget-modal').should('be.visible')
    cy.get('.views-form')
    cy.get('[data-drupal-selector="edit-media-library-select-form-5"]').check()
    cy.get('.ui-dialog-buttonset > button').click()
    cy.get('[data-drupal-selector="edit-field-navigation-banner-image-selection-0-rendered-entity"]').should('be.visible')
});

Cypress.Commands.add('taxonomyLinkEnglish', () => {
    //add link to menu and select taxonomy
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()
    cy.get('[data-drupal-selector="edit-menu-title"]').clear().type('Embarazo y primera infancia')
    cy.get('#edit-menu-node-menus-en-menu-parent').select('-- Life events')
 });

Cypress.Commands.add('taxonomyLinkSpanish', () => {
    //add link to menu and select taxonomy
    cy.get('#edit-advanced')
    cy.get('#edit-menu').click()
    cy.get('[data-drupal-selector="edit-menu"]')
    cy.get('#edit-menu-enabled').check()
    cy.get('[data-drupal-selector="edit-menu-title"]').clear().type('Embarazo y primera infancia')
    cy.get('#edit-menu-node-menus-es-menu-parent').select('-- Etapas importantes de la vida')
 });

Cypress.Commands.add('pagePublish', () => {
    //publish page
    cy.get("#edit-moderation-state-0-state").select("Published")
    cy.get('#edit-submit').click()
});

Cypress.Commands.add('pageDirectoryPublish', () => {
    //publish federal and state page
    cy.get('#edit-submit').click()
});

