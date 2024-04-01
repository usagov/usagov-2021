describe('Add a link to the left menu of a page', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a English left menu
    cy.get('ul > li > a').contains('Left Menu English').focus().click()
    //add a link
    cy.get('div#block-claro-local-actions > ul > li > a').click()
    //add a card to a new naviagion page
    cy.get('[data-drupal-selector="edit-title-0-value"]').type('this a test b')
    cy.get('[data-drupal-selector="edit-link-0-uri"]').type('Get food assistance with the WIC program (83)')
    cy.get('#edit-enabled-value').check()
    //cy.get('#edit-langcode-0-value').select('Español')
    //cy.get('[data-drupal-selector="edit-menu-parent"]').select('---- This is a test title')
    cy.get('[data-drupal-selector="edit-menu-parent"]').select('-- This is a test title')
    cy.get('#edit-submit').click()

    cy.visit('/testing/test1')

  })
})
