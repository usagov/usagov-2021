import * as utils from '../../support/utils.js'
import { pageObjects } from '../../support/pageObjects'
import * as EN_LOCALE_DATA from '../../../../benefit-finder/src/shared/locales/en/en.json'
import * as EN_DOLO_MOCK_DATA from '../../../../benefit-finder/src/shared/api/mock-data/current.json'
import * as BENEFITS_ELIGIBILITY_DATA from '../../fixtures/benefits-eligibility.json'

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
const reviewYourSelections =
  EN_LOCALE_DATA.reviewSelectionModal.buttonGroup[0].value

describe('Client Router Tests', () => {
  it("Should render the Intro component at '/death' life event", () => {
    cy.visit(utils.storybookUri)
    pageObjects
      .button()
      .contains(EN_LOCALE_DATA.intro.button)
      .should('be.visible')
  })

  it("Should render the LifeEventSection component at '/death/about-you'", () => {
    cy.visit(utils.storybookUri)
    cy.navigateToAboutTheApplicantPage()
  })

  it("should navigate to '/death/verify-selections' and render VerifySelectionsView", () => {
    cy.visit(utils.storybookUri)
    cy.navigateToModal({
      dateOfBirth,
      relationship,
      maritalStatus,
      dateOfDeath,
    })
    cy.clickButton(reviewYourSelections)
    cy.url().should('include', '/verify-selections')
    pageObjects.verifySelectionsView().should('exist')
  })

  it("Should navigate to '/death/results' and render ResultsView eligible items", () => {
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
    cy.url().should('include', '/results')
    pageObjects.benefitResultsView().should('be.visible')
    pageObjects.accordionHeading().should('have.length.greaterThan', 0)
  })

  it("Should navigate to '/death/results/not-eligible' and render ResultsView not-eligible items", () => {
    cy.visit(utils.storybookUri)
    cy.navigateToBenefitResultsPage({
      dateOfBirth,
      relationship,
      maritalStatus,
      dateOfDeath,
    })
    pageObjects.zeroBenefitsViewHeading().should('be.visible')
  })

  it('Should navigate back to the Intro component using the browser back button', () => {
    cy.visit(utils.storybookUri)
    cy.navigateToBenefitResultsPage({
      dateOfBirth,
      relationship,
      maritalStatus,
      dateOfDeath,
    })
    pageObjects.zeroBenefitsViewHeading().should('be.visible')
    cy.go('back')
    cy.go('back')
    cy.go('back')
    pageObjects
      .button()
      .contains(EN_LOCALE_DATA.intro.button)
      .should('be.visible')
  })

  it("Should redirect to '/results' when a valid query parameter is present", () => {
    const selectedData = BENEFITS_ELIGIBILITY_DATA.scenario_2_veteran.en.param
    const scenario = utils.encodeURIFromObject(selectedData)
    cy.visit(`${utils.storybookUri}${scenario}`)
    cy.url().should('include', '/results')
  })
})
