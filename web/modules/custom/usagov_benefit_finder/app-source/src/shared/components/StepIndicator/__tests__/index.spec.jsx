import { render } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import StepIndicator from '../index.jsx'
import content from '@api/mock-data/current.js'
// import * as en from '@locales/en/en.json'

const { data } = JSON.parse(content)
const { lifeEventForm } = data

describe('StepIndicator', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <StepIndicator
        current={1}
        data={lifeEventForm.sectionsEligibilityCriteria}
      />,
      {
        wrapper: BrowserRouter,
      }
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
