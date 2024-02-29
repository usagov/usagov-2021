describe('Local cms login', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)
    //login
    cy.visit('http://localhost/user/login')
    cy.get('[data-drupal-selector="edit-name"]').type('')
    cy.get('[data-drupal-selector="edit-pass"]').type('')
    cy.get('[data-drupal-selector="edit-submit"]').click()

    //logout
    cy.get('#toolbar-item-user').click()
    cy.get('#toolbar-item-user-tray').contains('Log out').click()
    //cy.get('#toolbar-bar')
  })
})
