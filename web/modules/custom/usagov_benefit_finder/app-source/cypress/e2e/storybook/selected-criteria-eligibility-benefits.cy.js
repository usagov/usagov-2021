/// <reference types="Cypress" />

import * as utils from '../../support/utils'
import * as EN_DOLO_MOCK_DATA from '../../../../benefit-finder/src/shared/api/mock-data/current.json'
import * as BENEFITS_ELIGIBILITY_DATA from '../../fixtures/benefits-eligibility.json'
import content from '../../../../benefit-finder/src/shared/api/mock-data/current.js'
import * as EN_LOCALE_DATA from '../../../../benefit-finder/src/shared/locales/en/en.json'
const { data } = JSON.parse(content)

// 18 years ago minus one day - applicant under 18 years old
// 1 day = 365.2425 (accounts for leap year)
const dateOfBirth = utils.getDateByOffset(-(18 * 365.2425 - 1))
// Date of death - 30 days ago
const dateOfDeath = utils.getDateByOffset(-30)

const relationship =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[1].fieldset.inputs[0].inputCriteria.values[1].value

const maritalStatus =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[2].fieldset.inputs[0].inputCriteria.values[1].value
const citizenshipStatusId =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[3].fieldset.inputs[0].inputCriteria.id
const paidIntoSocialSecurityId =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[1].section
    .fieldsets[2].fieldset.inputs[0].inputCriteria.id
const publicSafetyOfficerId =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[1].section
    .fieldsets[3].fieldset.inputs[0].inputCriteria.id

describe('Validate correct eligibility benefits display based on selected criteria/options', () => {
  it('Should render Survivor Benefits for Child benefit accordion correctly based on selected criteria options', () => {
    cy.visit(utils.storybookUri)

    cy.navigateToBenefitResultsPage({
      dateOfBirth,
      relationship,
      maritalStatus,
      optionalApplicantFields: {
        [citizenshipStatusId]: 0, // Select "Yes" for citizenship
      },
      dateOfDeath,
      optionalDeceasedFields: {
        [paidIntoSocialSecurityId]: 0, // Select "Yes" for "Did deceased ever work and pay U.S. Social Security taxes?"
        [publicSafetyOfficerId]: 0, // Select "Yes" for "Was the deceased a public safety officer who died in the line of duty"
      },
    })

    const accordionTitle = EN_DOLO_MOCK_DATA.data.benefits[23].benefit.title
    const eligibilityLabels =
      EN_DOLO_MOCK_DATA.data.benefits[23].benefit.eligibility.map(e => e.label)

    cy.validateAccordionContent(accordionTitle, eligibilityLabels)
  })

  it('qa scenario 1 Covid EN - Verify correct benefit results for query values that includes covid in search parameter of URL', () => {
    const selectedData = BENEFITS_ELIGIBILITY_DATA.scenario_1_covid.en.param
    const enResults = BENEFITS_ELIGIBILITY_DATA.scenario_1_covid.en.results
    const scenario = utils.encodeURIFromObject(selectedData)
    delete selectedData.shared // We don't want to include the "shared" param
    const selectDataLength = Object.keys(selectedData).length
    const benefitsCount = data.benefits.length

    cy.visit(`${utils.storybookUri}${scenario}`)

    // Validate accordion headings
    cy.validateAccordionHeadings(
      enResults.eligible.length,
      enResults.eligible.eligible_benefits,
      EN_LOCALE_DATA.resultsView.benefitAccordion.eligibleStatusLabels[0]
    )

    // Validate results view attributes
    cy.validateResultsViewAttributes(
      selectDataLength,
      benefitsCount,
      enResults.eligible.length,
      enResults.moreInformationNeeded.length
    )
  })

  it('QA scenario 2 Veteran EN - Verify correct benefit results for query values that includes veteran in search parameter of URL', () => {
    const selectedData = BENEFITS_ELIGIBILITY_DATA.scenario_2_veteran.en.param
    const enResults = BENEFITS_ELIGIBILITY_DATA.scenario_2_veteran.en.results
    const scenario = utils.encodeURIFromObject(selectedData)

    cy.visit(`${utils.storybookUri}${scenario}`)

    // Validate accordion headings
    cy.validateAccordionHeadings(
      enResults.eligible.length,
      enResults.eligible.eligible_benefits,
      EN_LOCALE_DATA.resultsView.benefitAccordion.eligibleStatusLabels[0]
    )
  })

  it('QA scenario 3 Coal Miner EN - Verify correct benefit results for query values that includes Coal Miner in search parameter of URL', () => {
    const selectedData =
      BENEFITS_ELIGIBILITY_DATA.scenario_3_coal_miner.en.param
    const enResults = BENEFITS_ELIGIBILITY_DATA.scenario_3_coal_miner.en.results
    const scenario = utils.encodeURIFromObject(selectedData)

    cy.visit(`${utils.storybookUri}${scenario}`)

    cy.validateAccordionHeadings(
      enResults.eligible.length,
      enResults.eligible.eligible_benefits,
      EN_LOCALE_DATA.resultsView.benefitAccordion.eligibleStatusLabels[0]
    )
  })

  it('Should display green check icons on eligible benefits', () => {
    const selectedData = BENEFITS_ELIGIBILITY_DATA.scenario_2_veteran.en.param
    const scenario = utils.encodeURIFromObject(selectedData)
    cy.visit(`${utils.storybookUri}${scenario}`)
    cy.validateGreenCheckIcons(selectedData)
  })

  it('Should display Zero benefit view when no benefit are eligible', () => {
    const selectedData = BENEFITS_ELIGIBILITY_DATA.zero_benefit_view.en.param
    const enResults = BENEFITS_ELIGIBILITY_DATA.zero_benefit_view.en.results
    const scenario = utils.encodeURIFromObject(selectedData)
    cy.visit(`${utils.storybookUri}${scenario}`)

    cy.validateZeroBenefitsView(
      enResults,
      EN_LOCALE_DATA.resultsView.zeroBenefits.heading,
      EN_LOCALE_DATA.resultsView.zeroBenefits.cta
    )
  })
})
