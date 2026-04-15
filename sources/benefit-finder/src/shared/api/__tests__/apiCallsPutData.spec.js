import * as apiCalls from '../apiCalls'
import content from '../mock-data/current'
const { data } = JSON.parse(content)

// Our method apiCalls.PUT.Data handles state management for selected values that are not in Date format.

// an async function that compares keys and strings.
// @function
// @param {string} criteriaKey
// @param {object} currentData
// @param {function} setCurrentData
// @param {string} eventTargetValue
// @return {function} updated state if successful

// Write a spec that takes in an object of values for the following:

// @param {string} criteriaKey
// @param {object} currentData
// @param {string} eventTargetValue
// executes * @param {function} setCurrentData

// and expects

// an update to currentData if a key match and value match is found
// change if key is found and new value is selected
// no change if key is found but no value match
// no change if no key is found but value matches
const stepDataArray = [...data.lifeEventForm.sectionsEligibilityCriteria]
const goodCriteriaKey = 'applicant_marital_status'
const badCriteriaKey = 'kjgljjlhkhgljgjh'
const goodString = 'Married'
const changeSelectedValue = 'Divorced'
const badString = 'hkshshslhshjksh'

let criteriaKey
let eventTargetValue
let currentData
let expectedUpdate

// mock useState Function
function setCurrentData(updatedData) {
  currentData = updatedData
}

beforeAll(() => {
  criteriaKey = goodCriteriaKey
  setCurrentData(stepDataArray[0])
  expectedUpdate =
    currentData.section.fieldsets[2].fieldset.inputs[0].inputCriteria.values
})

test('correctly update data after init state, key match and value match is found', async () => {
  expect(expectedUpdate[0].selected).toBe(undefined)
  eventTargetValue = goodString

  await apiCalls.PUT.Data(
    criteriaKey,
    stepDataArray[0],
    setCurrentData,
    eventTargetValue
  ).then(() => {
    expect(expectedUpdate[0]).toHaveProperty('selected', true)
  })
})

test('correctly update data after updated selected state', async () => {
  expect(expectedUpdate[0].selected).toBe(true)
  eventTargetValue = changeSelectedValue

  await apiCalls.PUT.Data(
    criteriaKey,
    stepDataArray[0],
    setCurrentData,
    eventTargetValue
  ).then(() => {
    expect(expectedUpdate[0].selected).toBe(undefined)
    expect(expectedUpdate[3]).toHaveProperty('selected', true)
  })
})

test('does not update data if values do not match', async () => {
  delete expectedUpdate[0].selected
  expect(expectedUpdate[0].selected).toBe(undefined)
  eventTargetValue = badString

  await apiCalls.PUT.Data(
    criteriaKey,
    stepDataArray[0],
    setCurrentData,
    eventTargetValue
  ).then(() => {
    expect(expectedUpdate[0].selected).toBe(undefined)
  })
})

test('does not update data if keys do not match', async () => {
  expect(expectedUpdate[0].selected).toBe(undefined)
  eventTargetValue = goodString
  criteriaKey = badCriteriaKey

  await apiCalls.PUT.Data(
    criteriaKey,
    stepDataArray[0],
    setCurrentData,
    eventTargetValue
  ).then(() => {
    expect(expectedUpdate[0].selected).toBe(undefined)
  })
})
