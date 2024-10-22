const paths = ["/site-issue-report-form", "/es/reporte-problemas-en-este-sitio-web"];

paths.forEach(path => {
  let lang;
  let thank_you;
  let recordType;

  if (path === "/site-issue-report-form") {
    lang = "English";
    thank_you = "https://www.usa.gov/thank-you-issue-report"
    recordType = "012U00000001eYv";
  } else {
    lang = "Español";
    thank_you = "https://www.usa.gov/es/gracias-por-reportar-problemas-en-este-sitio-web";
    recordType = "012U00000001eYr";
  }

  describe(`Report a Problem ${lang}`, () => {
    beforeEach(() => {
        cy.visit(path)
    })
    it('Test Salesforce hidden fields are present and valid', () => {
      cy.get('input[name="orgid"]')
        .should('have.value', '00DU0000000Leux')
      cy.get('input[name="retURL"]')
        .should('have.value', thank_you)
      cy.get('input[name="recordType"]')
        .should('have.value', recordType)
      cy.get('input[name="Sender_IP__c"]')
        .should('have.value', '192.168.1.1')
      cy.get('input[name="Site_Version__c"]')
        .should('have.value', 'New')
      cy.get('input[name="Call_Topic__c"]')
        .should('have.value', 'About USAGov')
      cy.get('input[name="Case_Topic_Other__c"]')
        .should('have.value', 'From USAGov Issue Form')
      cy.get('input[name="Gender__c"]')
        .should('have.value', 'Unknown')
      cy.get('input[name="external"]')
        .should('have.value', '1')
    })
  })

})