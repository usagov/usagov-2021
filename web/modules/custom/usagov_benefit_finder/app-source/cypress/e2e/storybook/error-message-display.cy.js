/// <reference types="Cypress" />

import { pageObjects } from '../../support/pageObjects'
import * as utils from '../../support/utils'
import * as EN_DOLO_MOCK_DATA from '../../../../benefit-finder/src/shared/api/mock-data/current.json'
import * as EN_LOCALE_DATA from '../../../../benefit-finder/src/shared/locales/en/en.json'
import 'cypress-plugin-tab'

const dateOfBirth = utils.getDateByOffset(-(18 * 365.2425 - 1))
const relationship =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[1].fieldset.inputs[0].inputCriteria.values[1].value
const maritalStatus =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[2].fieldset.inputs[0].inputCriteria.values[1].value

const alertHiddenState = {
  'aria-hidden': 'true', // expects a hidden error notice to have "aria-hidden=true"
  tabindex: '0', // expects a hidden error notice to have "tabindex=-0"
  role: 'alert', // expects a hidden error notice to have "role=alert"
  'aria-live': 'polite', // expects a top level visible error notice to have "aria-live="polite"
}

const alertDisplayState = {
  'aria-hidden': 'false', // expect a visible error notice to have "aria-hidden=false"
  tabindex: '0', // expect a visible error notice to have "tabindex=0"
  role: 'alert', // expects a hidden error notice to have "role=alert"
  'aria-live': 'assertive', // expect a top level visible error notice to have "aria-live="assertive""
}

beforeEach(() => {
  cy.visit(utils.storybookUri)
  cy.clickButton(EN_LOCALE_DATA.intro.button)
})

describe('Validate correct error messages display for negative scenarios', () => {
  it('Should load with error notice hidden and will have the correct aria attributes on load', () => {
    // expects the error notice to be hidden if no errors are present
    pageObjects.benefitSectionAlert().should('have.class', 'display-none')

    for (const attr in alertHiddenState) {
      pageObjects
        .benefitSectionAlert()
        .invoke('attr', attr)
        .should('eq', alertHiddenState[attr])
    }
  })

  it('Should display error message when user attempts to move forward without completing required field', () => {
    // expect when a user selects continue the focus is made on the error notice at the top
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.focused().should('have.class', 'usa-alert--error')

    // expect the error notice to be visible if errors are present
    pageObjects.benefitSectionAlert().should('not.have.class', 'display-none')

    for (const attr in alertDisplayState) {
      pageObjects
        .benefitSectionAlert()
        .invoke('attr', attr)
        .should('eq', alertDisplayState[attr])
    }
  })

  it('Should have a list of errors', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.focused().should('have.class', 'usa-alert--error').tab()
    pageObjects.bfAlertList().then(() => {
      pageObjects.bfAlertListItem().should('have.length.above', 0)
    })
  })

  it('Should have a list of errors that link to the invalid fields', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.focused().should('have.class', 'usa-alert--error').tab()
    // expect the first tabbable item in the list to be the first error link
    pageObjects.bfAlertList().then(() => {
      pageObjects.bfAlertListItem().then(errors => {
        cy.get(errors[0])
          .children()
          .invoke('attr', 'href')
          .then(href => {
            cy.focused().should('have.attr', 'href').and('include', href)
          })
      })
    })
  })

  it('Should allow tabbing to the next error message', () => {
    // expect when a user tabs from the focus error they can tab any other error notices in the form
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.focused().should('have.class', 'usa-alert--error').tab()
    pageObjects.bfAlertList().then(() => {
      pageObjects.bfAlertListItem().then(errors => {
        const errorsArray = [...errors]
        errorsArray.forEach(() => cy.focused().tab())
        cy.focused().should('have.class', 'usa-input--error')
      })
    })
  })

  it('Should hide the error message if field error is resolved', () => {
    // expect when a user tabs from the focus error they can tab any other error notices in the form
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.focused().should('have.class', 'usa-alert--error').tab()
    pageObjects.bfAlertList().then(() => {
      pageObjects.bfAlertListItem().then(errors => {
        const errorsArray = [...errors]
        errorsArray.forEach(() => cy.focused().tab())
        cy.focused().should('have.class', 'usa-input--error')
        // expect when a user has resolved all errors the top level error notices is not visible or accessible
        cy.fillDetailsAboutTheApplicant({
          dateOfBirth,
          relationship,
          maritalStatus,
        })
        // expect the error notice to be hidden if errors are present
        pageObjects.benefitSectionAlert().should('have.class', 'display-none')
      })
    })
  })

  it('Should not allow moving forward by clicking continue when error banner is present', () => {
    // expect when a user tabs from the focus error they can tab any other error notices in the form
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.focused().should('have.class', 'usa-alert--error').tab()
    pageObjects.bfAlertList().then(() => {
      pageObjects.bfAlertListItem().then(errors => {
        const errorsArray = [...errors]
        errorsArray.forEach(() => cy.focused().tab())
        cy.focused().should('have.class', 'usa-input--error')

        // expect date DOM structure alert to be accessible
        for (const attr in alertDisplayState) {
          pageObjects
            .benefitSectionAlert()
            .invoke('attr', attr)
            .should('eq', alertDisplayState[attr])
        }

        // expect when a user has resolved all errors the top level error notices is not visible or accessible
        cy.fillDetailsAboutTheApplicant({
          relationship,
        })

        // expect the error notice to be visible if errors are present
        pageObjects
          .benefitSectionAlert()
          .should('not.have.class', 'display-none')
        cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
        cy.focused().should('have.class', 'usa-alert--error')
        // expect date DOM structure alert to be accessible
        for (const attr in alertDisplayState) {
          pageObjects
            .benefitSectionAlert()
            .invoke('attr', attr)
            .should('eq', alertDisplayState[attr])
        }
      })
    })
  })

  it('Should include error count in alert', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    pageObjects
      .bfAlertList()
      .find('li')
      .its('length') // Get the count of 'li' elements using the base element
      .then(count => {
        // Assert that the heading contains the count
        pageObjects.alertHeading().should('include.text', count)
      })
  })

  it('Should include list of error links and clicking on a link navigates to a specific field', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)

    pageObjects
      .bfAlertList()
      .find('a')
      .each($el => {
        cy.wrap($el)
          .should('have.attr', 'href') // Check that each element has an 'href' attribute
          .and('not.be.empty') // Ensure the 'href' attribute is not empty
      })

    pageObjects
      .bfAlertList()
      .find('a')
      .each($el => {
        cy.wrap($el).then($link => {
          const href = $link.attr('href') // Get the href attribute value
          cy.get(href).focus()
          cy.get(href).should('have.focus') // Focus should be on the element
        })
      })
  })

  it('Should validate error label content overrides', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)

    const relationErrorMessageOverride =
      EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0]
        .section.fieldsets[1].fieldset.errorMessage
    pageObjects
      .errorDescription()
      .eq(1)
      .should('be.visible')
      .and('contain.text', relationErrorMessageOverride)

    pageObjects
      .errorDescription()
      .eq(0)
      .should('not.contain.text', relationErrorMessageOverride)
    pageObjects
      .dateOfBirthMonthError()
      .should('not.contain.text', relationErrorMessageOverride)
    pageObjects
      .dateOfBirthDayError()
      .should('not.contain.text', relationErrorMessageOverride)
    pageObjects
      .dateOfBirthYearError()
      .should('not.contain.text', relationErrorMessageOverride)
  })
})
