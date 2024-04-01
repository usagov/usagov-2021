describe('Create and delete a state directory page', () => {
  it('Gets, types and clicks to create a basic page', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a federal directory page
    cy.get('ul > li > a').contains('State Directory Record').focus().click()

    //fill out cms basic page
    cy.get("#edit-title-0-value").type("State Directory Record test title")
    cy.get('[data-drupal-selector="edit-field-page-intro-0-value"]').type("This is a test state record intro")
    cy.get("#edit-field-meta-description-0-value").type("This is a test state record meta description")

    //add English text to wysiwyg
    cy.textEnglish()

    //address
    //street 1
    cy.get('[data-drupal-selector="edit-field-street-1-0-value"]').type('100 Community Place')
    //street 2
    //cy.get('[data-drupal-selector="edit-field-street-2-0-value"]').type()
    //street 3
    //cy.get('[data-drupal-selector="edit-field-street-3-0-value"]').type()
    //city
    cy.get('[data-drupal-selector="edit-field-city-0-value"]').type('Annapolis')
    //state
    cy.get('#edit-field-state-abbr').select('MD')
    //zip
    cy.get('[data-drupal-selector="edit-field-zip-0-value"]').type('20405')

    //state website
    //url
    cy.get('#edit-field-website-0-uri').type('https://www.maryland.gov/Pages/default.aspx')
    //link text
    cy.get('#edit-field-website-0-title').type('Maryland.gov')

    //state contact
    //url
    cy.get('#edit-field-contact-link-0-uri').type('https://governor.maryland.gov/contact-us/Pages/default.aspx')
    //link text
    cy.get('#edit-field-contact-link-0-title').type('Contact Us')

    //governor
    //url
    cy.get('#edit-field-governor-0-uri').type('https://governor.maryland.gov/Pages/home.aspx')
    //link text
    cy.get('#edit-field-governor-0-title').type('Wes Moore')

    //governor contact
    //url
    cy.get('#edit-field-governor-contact-0-uri').type('https://md.accessgov.com/governor/Forms/Page/cs/contact-the-governor/1')
    //link text
    cy.get('#edit-field-governor-contact-0-title').type('Contact Wes Moore')

    //state attorney general
    //url
    cy.get('#edit-field-state-attorney-general-0-uri').type('https://www.marylandattorneygeneral.gov/')
    //link text
    cy.get('#edit-field-state-attorney-general-0-title').type('Anthony G Brown')

    //agriculture department
    //url
    cy.get('#edit-field-agriculture-department-0-uri').type('https://mda.maryland.gov/Pages/default.aspx')
    //link text
    cy.get('#edit-field-agriculture-department-0-title').type('Department of Agriculture')

    //consumer protection
    //url
    cy.get('#edit-field-consumer-protection-0-uri').type('https://mda.maryland.gov/Pages/default.aspx')
    //link text
    cy.get('#edit-field-consumer-protection-0-title').type('Consumer Affiars')

    //corrections department
    //url
    cy.get('#edit-field-corrections-dept-0-uri').type('https://dpscs.maryland.gov/')
    //link text
    cy.get('#edit-field-corrections-dept-0-title').type('Corretions')

    //local governents
    //url
    cy.get('#edit-field-local-governments-0-uri').type('https://www.maryland.gov/pages/agency_directory.aspx?view=Agencies')
    //link text
    cy.get('#edit-field-local-governments-0-title').type('Local Governments')

    //district attorneys
    //url
    cy.get('#edit-field-district-attorneys-0-uri').type('https://www.courts.state.md.us/')
    //link text
    cy.get('#edit-field-district-attorneys-0-title').type('District Attorney')

    //education department
    //url
    cy.get('#edit-field-education-department-0-uri').type('https://www.maryland.gov/pages/education.aspx')
    //link text
    cy.get('#edit-field-education-department-0-title').type('Education')

    //election office
    //url
    cy.get('#edit-field-election-office-0-uri').type('https://elections.maryland.gov/')
    //link text
    cy.get('#edit-field-election-office-0-title').type('Election Board')

    //emergency management agency
    //url
    cy.get('#edit-field-emergency-management-0-uri').type('https://mdem.maryland.gov/Pages/default.aspx')
    //link text
    cy.get('#edit-field-emergency-management-0-title').type('Emergency Management')

    //health department
    //url
    cy.get('#edit-field-health-department-0-uri').type('https://health.maryland.gov/Pages/Home.aspx')
    //link text
    cy.get('#edit-field-health-department-0-title').type('Health Department')

    //motor vehicle office
    //url
    cy.get('#edit-field-motor-vehicle-office-0-uri').type('https://mva.maryland.gov/Pages/default.aspx')
    //link text
    cy.get('#edit-field-motor-vehicle-office-0-title').type('Motor Vehicle Administration')

    //social services
    //url
    cy.get('#edit-field-social-services-0-uri').type('https://dgs.maryland.gov/Pages/default.aspx')
    //link text
    cy.get('#edit-field-social-services-0-title').type('Social Services')


    //email
    cy.get('#edit-field-email-0-value').type('abcd@yahoo.com')

    //phone number
    cy.get('#edit-field-phone-number-0-value').type('2025552211')


    //toll-free number
    cy.get('#edit-field-toll-free-number-0-value').type('3016667788')


    //tty number
    cy.get('#edit-field-tty-number-0-value').type('2403432299')

    //language toggle
    //cy.get('[data-drupal-selector="edit-field-language-toggle-0-target-id"]').type('Embarazo y primera infancia')

    //mothership uuid
    //cy.get().type()


    //fill out url alias
    cy.get('#edit-path-0').click()
    cy.get('#edit-path-0-alias').type('/testing/state-record1')

    //publish page
    cy.pageDirectoryPublish()

    //cy.screenshot('stateDirectoryRecord')

    //delete test page
    cy.get('ul > li > a').contains('Content').focus().click()
    cy.get('#edit-combine').type('State Directory Record test title')
    cy.get('#edit-submit-content').click()
    cy.get('#edit-node-bulk-form-0').check()
    cy.get('#edit-action').select('Delete content')
    cy.get('#edit-submit').click()
    cy.get('#edit-submit').click()
  })
})
