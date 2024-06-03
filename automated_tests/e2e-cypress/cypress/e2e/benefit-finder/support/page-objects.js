class PageObjects {
  menuButton() {
    return cy.get('.usa-menu-btn')
  }

  mobileMenu() {
    return cy.get('.usagov-mobile-menu')
  }

  breadCrumbList() {
    return cy.get('.usa-breadcrumb__list')
  }

  cardGroup() {
    return cy.get('.usa-card-group li')
  }
}

export const pageObjects = new PageObjects()
