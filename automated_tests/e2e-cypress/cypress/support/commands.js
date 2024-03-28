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

Cypress.Commands.add('pagePublish', () => {
    //publish page
    cy.get('#content-moderation-entity-moderation-form')
    cy.get('#edit-new-state').select('Published')
    cy.get('#edit-submit').click()
});

