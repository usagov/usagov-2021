describe('Create and delete a page', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    cy.get('ul > li > a').contains('Basic Page').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Get food assistance with the WIC program test")
    cy.get("#edit-field-page-intro-0-value").type("The Special Supplemental Nutrition Program for Women, Infants, and Children (WIC) can help you and your young children get food, nutrition counseling, and social service referrals. test")
    cy.get("#edit-field-meta-description-0-value").type("The Special Supplemental Nutrition Program for Women, Infants, and Children (WIC) can help you and your young children get food, nutrition counseling, and social service referrals. test")
    cy.get("#edit-field-short-description-0-value").type("This is a test page description")

    //Select page type
    cy.pageType()

    //Language toggle to Spanish page
    cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Obtenga asistencia alimentaria con el programa WIC')

    //select html option for wysiwyg
    //cy.get('#edit-body-0-format--2').select("HTML").should('have.value', 'html')

    //add English text to wysiwyg
    cy.textEnglish()

    //select navigation page image
    cy.imageSelect()

    //add taxonomy link to Spanish page
    cy.taxonomyLinkEnglish()

    //fill out url alias
    cy.get ('[data-drupal-selector="edit-path-0-alias"]').type('/food-assistance-test')

    //publish page
    cy.pagePublish()

    //Takes screenshot of the page
    //cy.screenshot('createBasicPage')

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('Get food assistance with the WIC program test')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
