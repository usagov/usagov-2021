describe('Local cms login', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a English left menu
    cy.get('ul > li > a').contains('Left Menu Spanish').focus().click()
    //add a link
    cy.get('div#block-claro-local-actions > ul > li > a').click()
    //add a card to a new naviagion page
    cy.get('[data-drupal-selector="edit-title-0-value"]').type('this a test b-espanol')
    cy.get('[data-drupal-selector="edit-link-0-uri"]').type('Obtenga asistencia alimentaria con el programa WIC (485)')
    cy.get('#edit-enabled-value').check()
    cy.get('#edit-langcode-0-value').select('Español')
    cy.get('[data-drupal-selector="edit-menu-parent"]').select('---- This is a test title Spanish')
    cy.get('#edit-submit').click()

  })
})
