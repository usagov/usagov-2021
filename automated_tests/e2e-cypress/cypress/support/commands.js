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