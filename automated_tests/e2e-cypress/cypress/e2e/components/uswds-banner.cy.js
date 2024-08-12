describe('USWDS-Banner', () => {
  beforeEach(() => {
      // Set base URL
      cy.visit('/')

      cy.injectAxe()
  })
  it('BTE 1: Sitewide banner for official government site appears at the top, accordion can be expanded', () => {
      cy.get('header')
          .find('.usa-banner__header')
          .should('be.visible')

      // Accordion content should not be visible
      cy.get('header')
          .find('.usa-banner__content')
          .should('not.be.visible')

      // Expand accordion
      cy.get('header')
          .find('.usa-accordion__button')
          .click()

      // Accordion content should be visible
      cy.get('.usa-banner__content')
          .should('be.visible')
  })
})