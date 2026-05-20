/// <reference types="Cypress" />

class PageObjects {
  accordionByTitle(title) {
    return this.accordionHeading().contains(new RegExp(`^${title}$`))
  }

  accordionHeading() {
    return cy.get('[data-testid="accordion-heading"]')
  }

  alertHeading() {
    return cy.get('[data-testid="bf-alert-heading"]')
  }

  benefitMemorableDate() {
    return cy.get('[data-testid="bf-usa-memorable-date"]')
  }

  benefitMemorableDateById(id) {
    return this.benefitMemorableDate().find(`[id$="${id}"]`)
  }

  benefitResultsView() {
    return cy.get('[data-testid="bf-result-view"]')
  }

  benefitSectionAlert() {
    return cy.get('[data-testid="alert"]')
  }

  benefitsAccordionLink(title) {
    return this.accordionHeading()
      .contains(new RegExp(`^${title}$`))
      .parent()
      .parent()
      .parent()
      .find('a[data-testid="bf-benefit-link"]')
  }

  bfAlertList() {
    return cy.get('[data-testid="bf-errors-list"]')
  }

  bfAlertListItem() {
    return cy.get('[data-testid="bf-errors-list-item"]')
  }

  breadCrumbList() {
    return cy.get('.usa-breadcrumb__list')
  }

  button() {
    return cy.get('.usa-button')
  }

  cardGroup() {
    return cy.get('.usa-card-group li')
  }

  dateOfBirthDayError() {
    return cy.get('[data-testid="day-error-description"]')
  }

  dateOfBirthMonthError() {
    return cy.get('[data-testid="month-error-description"]')
  }

  dateOfBirthYearError() {
    return cy.get('[data-testid="year-error-description"]')
  }

  errorDescription() {
    return cy.get('[data-testid="error-description"]')
  }

  expandAll() {
    return cy.get('[data-testid="bf-expand-all"]')
  }

  fieldset() {
    return cy.get('[data-testid="fieldset"]')
  }

  fieldsetById(id) {
    return this.fieldset().find(`[id^="${id}"]`)
  }

  iconGreenCheck() {
    return cy.get('[data-testid="icon-green-check"]')
  }

  menuButton() {
    return cy.get('.usa-menu-btn')
  }

  mobileMenu() {
    return cy.get('.usagov-mobile-menu')
  }

  modalButtonGroup() {
    return cy.get('[data-testid="modal-button-group"]')
  }

  notEligibleResultsButton() {
    return cy.get('[data-testid="bf-result-view-unmet-button"]')
  }

  radioGroup() {
    return cy.get('[data-testid="radio-group"]')
  }

  seeAllBenefitsButton() {
    return cy.get('[data-testid="zero-benefits-view-cta-button"]')
  }

  verifySelectionsView() {
    return cy.get('[data-testid="bf-verify-selections-view"]')
  }

  zeroBenefitsViewHeading() {
    return cy.get('[data-testid="zero-benefits-view-heading"]')
  }
}

export const pageObjects = new PageObjects()
