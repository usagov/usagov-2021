import { pageObjects } from './support/page-objects'

describe('Validate user can navigate each path of mobile menu and breadcrumb displays correctly', () => {
  beforeEach(() => {
    cy.viewport(320, 480)
    cy.visit('/benefit-finder')
  })

  context('Validate English menus and breadcrumb', () => {
    it('Should navigate to Benefit Finder page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('government benefits').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Home')
        .and('contain', 'Government benefits')
    })
  })

  context('Validate Spanish menus and breadcrumb', () => {
    beforeEach(() => {
      cy.get('.language-link').click()
    })

    it('Should navigate to Buscador de beneficios page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Encuentre beneficios').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Página principal')
        .and('contain', 'Encuentre beneficios')
    })
  })
})
