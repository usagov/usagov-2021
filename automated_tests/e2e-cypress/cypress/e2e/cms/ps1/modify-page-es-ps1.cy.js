describe('Local cms login', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //URL of page to edit
    cy.visit('es/ayuda-emergencia-vivienda')

    //Take screenshot of page
    //cy.screenshot('beforePageEdit')

    //Select the edit button to edit the page
    cy.get('#block-usagov-local-tasks')
    cy.get('[data-drupal-link-system-path="node/569/edit"]').click()

    //Edit page title
    cy.get("#edit-title-0-value").type("{moveToEnd} test")

    //Edit Page Intro
    cy.get("#edit-field-page-intro-0-value").type("{moveToEnd} This is a test.")

    //select state of page
    cy.get("#edit-moderation-state-0-state").select("Published")

    //Save page
    //cy.get('[ data-drupal-selector="edit-submit" ]').click()

    //Take screenshot of page
    //cy.screenshot('afterPageEdit')
  })
})
