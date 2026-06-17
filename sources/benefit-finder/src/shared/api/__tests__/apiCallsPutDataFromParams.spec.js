import * as apiCalls from '../../api/apiCalls'
import content from '../../api/mock-data/current.js'
const { data } = JSON.parse(content)

const scenarios = {
  death: [
    {
      scenario: 1,
      windowQuery:
        '?applicant_date_of_birth=%7B"month"%3A"3"%2C"day"%3A"5"%2C"year"%3A"1960"%7D&applicant_relationship_to_the_deceased=Spouse&applicant_marital_status=Widowed&applicant_citizen_status=Yes&applicant_care_for_child=Yes&applicant_paid_funeral_expenses=Yes&deceased_date_of_death=%7B"month"%3A"1"%2C"day"%3A"3"%2C"year"%3A"2022"%7D&deceased_death_location_is_US=Yes&deceased_paid_into_SS=Yes&deceased_public_safety_officer=No&deceased_miner=No&deceased_american_indian=No&deceased_died_of_COVID=Yes&deceased_served_in_active_military=No&shared=true%27',
      eval: true,
    },
  ],
}

const sharedToken = 'shared'
let stepDataArray
let expectedUpdate
let benefitsArray

// mock useState Function
function setBenefitsArray(updatedData) {
  benefitsArray = updatedData
}

beforeAll(() => {
  stepDataArray = [...data.lifeEventForm.sectionsEligibilityCriteria]
  benefitsArray = [...data.benefits]
  setBenefitsArray(benefitsArray)
  apiCalls.PUT.DataFromParams(
    scenarios.death[0].windowQuery,
    stepDataArray,
    setBenefitsArray,
    benefitsArray,
    sharedToken
  )

  expectedUpdate =
    stepDataArray[0].section.fieldsets[1].fieldset.inputs[0].inputCriteria
      .values
})

// confirm updated data return
test('scenario 1 updates data', () => {
  expect(expectedUpdate[0]).toHaveProperty('selected', true)
})
