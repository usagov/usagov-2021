import * as apiCalls from '../apiCalls'
import content from '../mock-data/current'
const { data } = JSON.parse(content)

// Our method EligibilityByCriteria is a function that updates the data determined by eligibility of benefits from selected values

// Write a spec that takes in the following parameters

// /**
// @function
// @param {array} selectedCriteria - array of selected fieldset values
// @param {array} data - array of benefits
// */

// and outputs an updated array of benefits based on the selected values
const stepDataArray = [...data.lifeEventForm.sectionsEligibilityCriteria]
const benefits = [...data.benefits]
const criteriaKey = 'applicant_relationship_to_the_deceased'
const selectedValue = 'Spouse'
const criteriaKeyDate = 'applicant_date_of_birth'
const selectedDate = { month: 0, year: 1978, day: 1 }

let eventTargetValue
let currentData
let expectedUpdate
let expectedDateUpdate

// mock useState Function
function setCurrentData(updatedData) {
  currentData = updatedData
}

beforeAll(() => {
  setCurrentData(stepDataArray[0])
  eventTargetValue = selectedValue
  expectedUpdate =
    currentData.section.fieldsets[1].fieldset.inputs[0].inputCriteria.values
  expectedDateUpdate =
    currentData.section.fieldsets[0].fieldset.inputs[0].inputCriteria.values
})

test('correctly update data after init state, key match and value match is found', async () => {
  expect(expectedUpdate[0].selected).toBe(undefined)

  await apiCalls.PUT.Data(
    criteriaKey,
    currentData,
    setCurrentData,
    eventTargetValue
  ).then(() => {
    expect(expectedUpdate[0]).toHaveProperty('selected', true)
  })
})

test('correctly update date data after init state, key match and value match is found', async () => {
  expect(expectedUpdate[0].selected).toBe(true)

  await apiCalls.PUT.DataDate(
    criteriaKeyDate,
    currentData,
    setCurrentData,
    selectedDate.month,
    'applicant_date_of_birth_0-date_of_birth_month'
  ).then(() => {
    apiCalls.PUT.DataDate(
      criteriaKeyDate,
      currentData,
      setCurrentData,
      selectedDate.year,
      'applicant_date_of_birth_0-date_of_birth_year'
    ).then(() => {
      apiCalls.PUT.DataDate(
        criteriaKeyDate,
        currentData,
        setCurrentData,
        selectedDate.day,
        'applicant_date_of_birth_0-date_of_birth_day'
      ).then(() => {
        expect(expectedDateUpdate[0]).toHaveProperty('selected', true)
        expect(expectedDateUpdate[0].value).toHaveProperty(
          'month',
          selectedDate.month
        )
        expect(expectedDateUpdate[0].value).toHaveProperty(
          'year',
          selectedDate.year
        )
        expect(expectedDateUpdate[0].value).toHaveProperty(
          'day',
          selectedDate.day
        )
      })
    })
  })
})

test('correctly returns eligibility state based on selected values', async () => {
  const selectedValues = apiCalls.GET.SelectedValueAll(stepDataArray)

  expect(selectedValues[0].values).toHaveProperty('value', selectedDate)
  expect(selectedValues[0].values).toHaveProperty('selected', true)
  expect(selectedValues[1].values).toHaveProperty('value', selectedValue)
  expect(selectedValues[1].values).toHaveProperty('selected', true)

  const eligibility = apiCalls.GET.EligibilityByCriteria(
    selectedValues,
    benefits
  )

  const eligibleBenefits = eligibility[1].benefit.eligibility.filter(
    item => item.isEligible === true
  )

  expect(eligibleBenefits[0]).toHaveProperty('criteriaKey', criteriaKey)
  expect(eligibleBenefits[0].acceptableValues).toContain(selectedValue)
  expect(eligibleBenefits[0]).toHaveProperty('isEligible', true)

  const notEligibleBenefits = eligibility[17].benefit.eligibility.filter(
    item => item.isEligible === false
  )

  expect(notEligibleBenefits[0]).toHaveProperty('criteriaKey', criteriaKeyDate)
  expect(notEligibleBenefits[0].acceptableValues).toContain('<18years')
  expect(notEligibleBenefits[0]).toHaveProperty('isEligible', false)

  expect(notEligibleBenefits[1]).toHaveProperty('criteriaKey', criteriaKey)
  expect(notEligibleBenefits[1].acceptableValues).not.toContain(selectedValue)
  expect(notEligibleBenefits[1]).toHaveProperty('isEligible', false)
})
