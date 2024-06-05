import { pageObjects } from './support/page-objects'

describe('Validate user can navigate each path of mobile menu and breadcrumb displays correctly', () => {
  beforeEach(() => {
    cy.viewport(320, 480)
    cy.visit('/benefit-finder')
  })

  context('Validate English menus and breadcrumb', () => {
    it('Should navigate to Benefit Finder page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Benefit finder').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Home')
        .and('contain', 'Government benefits')
        .and('contain', 'Benefit finder')
    })

    it('Should navigate to Death of a Loved One page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Death of a loved one').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Home')
        .and('contain', 'Government benefits')
        .and('contain', 'Benefit finder')
        .and('contain', 'Death of a loved one')
    })

    it('Should navigate to Retirement page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Retirement').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Home')
        .and('contain', 'Government benefits')
        .and('contain', 'Benefit finder')
        .and('contain', 'Retirement')
    })

    it('Should navigate to Disability page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Disability').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Home')
        .and('contain', 'Government benefits')
        .and('contain', 'Benefit finder')
        .and('contain', 'Disability')
    })
  })

  context('Validate Spanish menus and breadcrumb', () => {
    beforeEach(() => {
      cy.get('.language-link').click()
    })

    it('Should navigate to Buscador de beneficios page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Buscador de beneficios').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Página principal')
        .and('contain', 'Ayuda económica y beneficios del Gobierno')
        .and('contain', 'Buscador de beneficios')
    })

    it('Should navigate to Muerte de un ser querido page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Muerte de un ser querido').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Página principal')
        .and('contain', 'Ayuda económica y beneficios del Gobierno')
        .and('contain', 'Buscador de beneficios')
        .and('contain', 'Muerte de un ser querido')
    })

    it('Should navigate to Jubilación page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Jubilación').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Página principal')
        .and('contain', 'Ayuda económica y beneficios del Gobierno')
        .and('contain', 'Buscador de beneficios')
        .and('contain', 'Jubilación')
    })

    it('Should navigate to Discapacidad page', () => {
      pageObjects.menuButton().click()
      pageObjects.mobileMenu().contains('Discapacidad').click()
      pageObjects
        .breadCrumbList()
        .should('contain', 'Página principal')
        .and('contain', 'Ayuda económica y beneficios del Gobierno')
        .and('contain', 'Buscador de beneficios')
        .and('contain', 'Discapacidad')
    })
  })
})
