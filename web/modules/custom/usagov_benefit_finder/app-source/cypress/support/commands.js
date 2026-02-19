// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

import { pageObjects } from './pageObjects'
import * as EN_DOLO_MOCK_DATA from '../../../benefit-finder/src/shared/api/mock-data/current.json'
import * as EN_LOCALE_DATA from '../../../benefit-finder/src/shared/locales/en/en.json'

const maritalStatusId =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[2].fieldset.inputs[0].inputCriteria.id
const relationshipId =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[1].fieldset.inputs[0].inputCriteria.id
const startFindingBenefitsButton = EN_LOCALE_DATA.intro.button
const nextButtonGroup = EN_LOCALE_DATA.buttonGroup[1].value
const getYourResultsButton =
  EN_LOCALE_DATA.reviewSelectionModal.buttonGroup[1].value

Cypress.Commands.add('enterDate', (month, day, year) => {
  pageObjects.benefitMemorableDateById('month').select(month)
  pageObjects.benefitMemorableDateById('day').type(day)
  pageObjects.benefitMemorableDateById('year').type(year)
})

Cypress.Commands.add('clickButton', buttonText => {
  pageObjects.button().contains(buttonText).click()
})

Cypress.Commands.add('fillDetailsAboutTheApplicant', data => {
  const { dateOfBirth, relationship, maritalStatus, optionalFields = {} } = data

  // Fill mandatory fields if provided
  if (dateOfBirth) {
    cy.enterDate(dateOfBirth.month, dateOfBirth.day, dateOfBirth.year)
  }

  if (relationship) {
    cy.selectDropdownValue(relationshipId, relationship)
  }

  if (maritalStatus) {
    cy.selectDropdownValue(maritalStatusId, maritalStatus)
  }

  // Fill optional fields dynamically
  Object.entries(optionalFields).forEach(([fieldId, optionIndex]) => {
    cy.selectRadioByIdAndIndex(fieldId, optionIndex)
  })
})

Cypress.Commands.add('fillDetailsAboutTheDeceased', data => {
  const { dateOfDeath, optionalFields = {}, additionalFields = {} } = data

  // Fill mandatory fields if provided
  if (dateOfDeath) {
    cy.enterDate(dateOfDeath.month, dateOfDeath.day, dateOfDeath.year)
  }

  // Fill optional "Yes/No" fields dynamically
  Object.entries(optionalFields).forEach(([fieldId, optionIndex]) => {
    cy.selectRadioByIdAndIndex(fieldId, optionIndex)
  })

  // Fill additional dropdown fields displayed on certain conditions
  Object.entries(additionalFields).forEach(([fieldId, value]) => {
    cy.selectDropdownValue(fieldId, value)
  })
})

Cypress.Commands.add('navigateToAboutTheApplicantPage', () => {
  cy.clickButton(startFindingBenefitsButton)
  pageObjects.benefitMemorableDateById('month').should('exist')
})

Cypress.Commands.add(
  'navigateToAboutTheDeceasedPage',
  ({ dateOfBirth, relationship, maritalStatus, optionalFields = {} }) => {
    cy.navigateToAboutTheApplicantPage()
    cy.fillDetailsAboutTheApplicant({
      dateOfBirth,
      relationship,
      maritalStatus,
      optionalFields,
    })
    cy.clickButton(nextButtonGroup) // Navigate to the next step
  }
)

Cypress.Commands.add(
  'navigateToModal',
  ({
    dateOfBirth,
    relationship,
    maritalStatus,
    optionalApplicantFields = {},
    dateOfDeath,
    optionalDeceasedFields = {},
    additionalDeceasedFields = {},
  }) => {
    cy.navigateToAboutTheDeceasedPage({
      dateOfBirth,
      relationship,
      maritalStatus,
      optionalFields: optionalApplicantFields,
    })
    cy.fillDetailsAboutTheDeceased({
      dateOfDeath,
      optionalFields: optionalDeceasedFields,
      additionalFields: additionalDeceasedFields,
    })
    cy.clickButton(nextButtonGroup) // Proceed to open modal
    pageObjects
      .button(EN_LOCALE_DATA.reviewSelectionModal.buttonGroup[0].value)
      .should('exist')
  }
)

Cypress.Commands.add(
  'navigateToBenefitResultsPage',
  ({
    dateOfBirth,
    relationship,
    maritalStatus,
    optionalApplicantFields = {},
    dateOfDeath,
    optionalDeceasedFields = {},
    additionalDeceasedFields = {},
  }) => {
    // Reuse navigateToModal
    cy.navigateToModal({
      dateOfBirth,
      relationship,
      maritalStatus,
      optionalApplicantFields,
      dateOfDeath,
      optionalDeceasedFields,
      additionalDeceasedFields,
    })

    // Click the "Get Your Results" button to navigate to the results page
    cy.clickButton(getYourResultsButton)
  }
)

Cypress.Commands.add('selectDropdownValue', (fieldId, value) => {
  pageObjects.fieldsetById(fieldId).select(value)
})

Cypress.Commands.add('selectRadioByIdAndIndex', (fieldId, optionIndex = 0) => {
  pageObjects.fieldsetById(fieldId).eq(optionIndex).click({ force: true })
})

Cypress.Commands.add('validateAccordionContent', (accordionTitle, labels) => {
  pageObjects
    .accordionByTitle(accordionTitle)
    .click()
    .parent()
    .parent()
    .parent()
    .find('.bf-key-eligibility-criteria-list li')
    .each(($li, index) => {
      cy.wrap($li).should('contain', labels[index])
    })
})

// Validate accordion headings
Cypress.Commands.add(
  'validateAccordionHeadings',
  (visibleHeadingsLength, eligibleBenefits, eligibleLabel) => {
    pageObjects
      .accordionHeading()
      .filter(':visible')
      .should('have.length', visibleHeadingsLength)
      .and('contain', eligibleLabel)
    // Dynamically validate each eligible benefit
    eligibleBenefits.forEach(benefit => {
      pageObjects
        .accordionHeading()
        .filter(':visible')
        .should('contain', benefit)
    })
  }
)

Cypress.Commands.add(
  'validateAriaInvalid',
  (methodName, expectedValue, ...args) => {
    // Dynamically call the pageObjects method and retrieve the element
    pageObjects[methodName](...args)
      .invoke('attr', 'aria-invalid')
      .should('eq', expectedValue)
  }
)

// Validate benefit results view attributes
Cypress.Commands.add(
  'validateResultsViewAttributes',
  (selectDataLength, benefitsCount, eligibleCount, moreInfoCount) => {
    pageObjects
      .benefitResultsView()
      .should('exist') // Ensure the element exists
      .then(resultsView => {
        cy.wrap(resultsView)
          .invoke('attr', 'data-testid')
          .should('eq', 'bf-result-view')

        cy.wrap(resultsView)
          .invoke('attr', 'data-test-results-view')
          .should('eq', 'bf-eligible-view')

        cy.wrap(resultsView)
          .invoke('attr', 'data-test-results-view-criteria-values')
          .should('eq', `${selectDataLength}`)

        cy.wrap(resultsView)
          .invoke('attr', 'data-test-results-view-benefits')
          .should('eq', `${benefitsCount}`)

        cy.wrap(resultsView)
          .invoke('attr', 'data-test-results-view-eligible')
          .should('eq', `${eligibleCount}`)

        cy.wrap(resultsView)
          .invoke('attr', 'data-test-results-view-more-info')
          .should('eq', `${moreInfoCount}`)

        cy.wrap(resultsView)
          .invoke('attr', 'data-test-results-view-not-eligible')
          .should('eq', `${benefitsCount - eligibleCount - moreInfoCount}`)
      })
  }
)

Cypress.Commands.add('validateGreenCheckIcons', () => {
  pageObjects.expandAll().click()
  pageObjects.iconGreenCheck().should('exist')
})

Cypress.Commands.add(
  'validateZeroBenefitsView',
  (enResults, zeroBenefitsHeading, zeroBenefitsCTA) => {
    pageObjects.zeroBenefitsViewHeading().should('contain', zeroBenefitsHeading)

    pageObjects
      .accordionHeading()
      .filter(':visible')
      .should('have.length', enResults.eligible.length)

    pageObjects.seeAllBenefitsButton().should('contain', zeroBenefitsCTA)
  }
)
