import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import ResultsView from '../index.jsx'
import * as apiCalls from '@api/apiCalls.js'
import * as en from '@locales/en/en.json'
import content from '@api/mock-data/current.js'
const { data } = JSON.parse(content)

// get current data - subtract two years
const generateDOD = () => {
  const currentDate = new Date()
  return {
    month: currentDate.getMonth() + 1,
    day: currentDate.getDate(),
    year: currentDate.getFullYear() - 2,
  }
}

const params = {
  applicant_date_of_birth: {
    month: 4,
    day: 5,
    year: 1960,
  },
  applicant_relationship_to_the_deceased: 'Spouse',
  applicant_marital_status: 'Widowed',
  applicant_citizen_status: 'Yes',
  applicant_care_for_child: 'Yes',
  applicant_paid_funeral_expenses: 'Yes',
  deceased_date_of_death: generateDOD(),
  deceased_death_location_is_US: 'Yes',
  deceased_paid_into_SS: 'Yes',
  deceased_public_safety_officer: 'No',
  deceased_american_indian: 'No',
  deceased_died_of_COVID: 'Yes',
  deceased_served_in_active_military: 'No',
  shared: 'true',
}

export const encodeURIFromObject = obj => {
  return Object.entries(obj)
    .map(
      ([key, value]) =>
        `${encodeURIComponent(key)}=${
          typeof value === 'object'
            ? encodeURIComponent(JSON.stringify(value)) // handles date objects
            : encodeURIComponent(value)
        }`
    )
    .join('&')
}

const scenarios = {
  death: [
    {
      scenario: 1,
      windowQuery: `?${encodeURIFromObject(params)}`,
    },
  ],
}

const windowQuery = scenarios.death[0].windowQuery // Returns:'?q=123'
const sharedToken = 'shared'
let stepDataArray
let benefitsArray
let expectedUpdate
// mock useState Function
function setBenefitsArray(updatedData) {
  benefitsArray = updatedData
}

beforeAll(() => {
  // handle window.scrollTo
  const noop = () => {}
  Object.defineProperty(window, 'scrollTo', { value: noop, writable: true })
  stepDataArray = [...data.lifeEventForm.sectionsEligibilityCriteria]
  benefitsArray = [...data.benefits]
  setBenefitsArray(benefitsArray)
  apiCalls.PUT.DataFromParams(
    windowQuery,
    stepDataArray,
    setBenefitsArray,
    benefitsArray,
    sharedToken
  )

  expectedUpdate =
    stepDataArray[0].section.fieldsets[1].fieldset.inputs[0].inputCriteria
      .values
})

// render view without data
test('loads view', async () => {
  const view = render(
    <ResultsView
      stepDataArray={stepDataArray}
      relevantBenefits={data.lifeEventForm?.relevantBenefits}
      data={benefitsArray}
      notEligibleView={false}
      ui={en.resultsView}
    />,
    { wrapper: BrowserRouter }
  )

  await screen.findByTestId('bf-result-view')
  await screen.findByTestId('bf-share-trigger')
  await screen.findAllByTestId('bf-usa-accordion')
  await screen.findByTestId('dom-ready')

  expect(view.baseElement).toMatchSnapshot()
})

// render view with data
test('scenario 1 loads in view with the correct amount of likely eligible items', async () => {
  expect(expectedUpdate[0]).toHaveProperty('selected', true)
  const view = render(
    <ResultsView
      stepDataArray={stepDataArray}
      relevantBenefits={data.lifeEventForm?.relevantBenefits}
      data={benefitsArray}
      notEligibleView={false}
      ui={en.resultsView}
    />,
    { wrapper: BrowserRouter }
  )

  await screen.findByTestId('bf-result-view')
  await screen.findByTestId('bf-share-trigger')

  const eligibility = apiCalls.GET.EligibilityByCriteria(
    stepDataArray,
    benefitsArray
  )

  const e = eligibility.filter(item => {
    const f = item.benefit.eligibility.filter(item => item.isEligible === true)
    return f.length === item.benefit.eligibility.length
  })

  const n = eligibility.filter(item => {
    const f = item.benefit.eligibility.filter(item => item.isEligible === false)
    return f.length > 0
  })

  const m = eligibility.filter(item => {
    const f = item.benefit.eligibility.filter(item => item.isEligible !== false)
    return f.length >= 0
  })

  expect(e.length).toBe(5)
  expect(n.length).toBe(24)
  expect(m.length - e.length - n.length).toBe(1)

  await screen.findAllByTestId('bf-usa-accordion')
  await screen.findByTestId('dom-ready')

  expect(view.baseElement).toMatchSnapshot()
})
