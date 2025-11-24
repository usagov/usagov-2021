import { pageObjects } from './support/page-objects'

describe('Validate benefit-finder card on benefits page', () => {
  it('Should navigate to Benefit finder when clicking on Benefit finder card', () => {
    cy.visit('/benefits')
    pageObjects.cardGroup().contains('benefit finder tool').click()
    pageObjects
      .breadCrumbList()
      .should('contain', 'Home')
      .and('contain', 'Government benefits')
      .and('contain', 'government benefits')
  })
})
