describe('Create and delete a federal directory page', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a federal directory page
    cy.get('ul > li > a').contains('Federal Directory Record').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("Federal Directory Record test title")
    cy.get("#edit-field-page-intro-0-value").type("This is a test federal record intro")
    cy.get("#edit-field-meta-description-0-value").type("This is a test federal record meta description")

    //add English text to wysiwyg
    cy.textEnglish()

    //add federal record website url
    cy.get('#edit-field-website-0-uri').type('https://www.gsa.gov/')
    //add federal record website link text
    cy.get('#edit-field-website-0-title').type('General Services Administration')

    //button to add another input for url and link text
    //cy.get('#edit-field-website-add-more--zRNrSpFhaI4')

    //contact
    //contact url
    cy.get('#edit-field-contact-link-0-uri').type('https://www.gsa.gov/about-us/contact-us')
    //contact text link
    cy.get('#edit-field-contact-link-0-title').type('Contact Us')

    //find an office near you
    //office near you url
    cy.get('#edit-field-offices-near-you-0-uri').type('https://www.gsa.gov/about-us/regions/region-11-national-capital?gsaredirect=region11')
    //office near you link text
    cy.get('#edit-field-offices-near-you-0-title').type('National Capital Region')

    //phone number
    cy.get('#edit-field-phone-number-0-value').type('2025552211')


    //toll-free number
    cy.get('#edit-field-toll-free-number-0-value').type('3016667788')


    //tty number
    cy.get('#edit-field-tty-number-0-value').type('2403432299')


    //email
    cy.get('#edit-field-email-0-value').type('abcd@yahoo.com')


    //address
    //street 1
    cy.get('[data-drupal-selector="edit-field-street-1-0-value"]').type('640 E Street NW')
    //street 2
    //cy.get('[data-drupal-selector="edit-field-street-2-0-value"]').type()
    //street 3
    //cy.get('[data-drupal-selector="edit-field-street-3-0-value"]').type()
    //city
    cy.get('[data-drupal-selector="edit-field-city-0-value"]').type('Washington')
    //state
    cy.get('#edit-field-state-abbr').select('DC')
    //zip
    cy.get('[data-drupal-selector="edit-field-zip-0-value"]').type('20405')


    //government branch
    cy.get('#edit-field-government-branch').select('Executive Department')

    //language toggle
    //cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')

    //acronym
    cy.get('#edit-field-acronym-0-value').type('GSA')

    //mothership uuid
    //cy.get().type()

    //fill out url alias
    cy.get('#edit-path-0').click()
    cy.get('#edit-path-0-alias').type('/testing/federal-record1')

    //publish page
    cy.pageDirectoryPublish()

    //cy.screenshot('federalDirectoryRecord')

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('Federal Directory Record test title')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
