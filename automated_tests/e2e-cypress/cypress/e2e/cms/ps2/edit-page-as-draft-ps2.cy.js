describe('Edit page as a draft', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //URL of page to edit
    cy.visit('food-help')

    //Select the edit button to edit the page
    cy.get('#block-usagov-local-tasks')
    cy.get('[data-drupal-link-system-path="node/120/edit"]').click()

    //Edit Page Intro
    cy.get("#edit-field-page-intro-0-value").type("{moveToEnd} This is a test.")

    //select state of page
    cy.get("#edit-moderation-state-0-state").select("Draft")

    //Save page
    cy.get('[ data-drupal-selector="edit-submit" ]').click()

    //cy.screenshot('/cypress/screenshots/saveDraft23')

    cy.get('#block-usagov-local-tasks')
    cy.get('[data-drupal-link-system-path="node/120/revisions"]').click()

    cy.get('[data-drupal-selector="edit-node-revisions-table-0-operations"] > li.dropbutton-toggle > button.dropbutton__toggle').click()
    cy.get('li.delete > a').contains('Delete').click()
    cy.get('#edit-submit').click()

    cy.get('[data-original-order="0"] > a').click()
    //cy.screenshot('deleteDraft')
  })
})
