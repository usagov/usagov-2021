/// <reference types="Cypress" />

import * as utils from '../../support/utils.js'
import * as EN_LOCALE_DATA from '../../../src/shared/locales/en/en.json'
import * as EN_DOLO_MOCK_DATA from '../../../src/shared/api/mock-data/current.json'

const dateOfBirth = utils.getDateByOffset(-(18 * 365.2425 - 1))
const relationship =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[1].fieldset.inputs[0].inputCriteria.values[1].value
const relationshipId =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[1].fieldset.inputs[0].inputCriteria.id
const maritalStatus =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[2].fieldset.inputs[0].inputCriteria.values[1].value

describe('Validate state of aria-invalid attribute', () => {
  beforeEach(() => {
    cy.visit(utils.storybookUri)
    cy.navigateToAboutTheApplicantPage()
  })

  it('Should have default state of "false" for select, input, and radio', () => {
    cy.validateAriaInvalid('fieldsetById', 'false', relationshipId)
    cy.validateAriaInvalid('benefitMemorableDateById', 'false', 'day')
    cy.validateAriaInvalid('radioGroup', 'false')
  })

  it('Should have state of "true" when a required field has no value', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)
    cy.validateAriaInvalid('fieldsetById', 'true', relationshipId)
    cy.validateAriaInvalid('benefitMemorableDateById', 'true', 'day')
  })

  it('Should have state of "false" when previous was true but error has been resolved', () => {
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)

    cy.validateAriaInvalid('fieldsetById', 'true', relationshipId)
    cy.validateAriaInvalid('benefitMemorableDateById', 'true', 'day')

    cy.fillDetailsAboutTheApplicant({
      dateOfBirth,
      relationship,
      maritalStatus,
    })

    cy.validateAriaInvalid('fieldsetById', 'false', relationshipId)
    cy.validateAriaInvalid('benefitMemorableDateById', 'false', 'day')
  })
})
