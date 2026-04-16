import { render } from '@testing-library/react'
import NotEligibleBenefitsHeading from '../index.jsx'
import * as en from '@locales/en/en.json'

const { notEligible, summaryBox } = en

describe('NotEligibleBenefitsHeading', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <NotEligibleBenefitsHeading ui={{ notEligible, summaryBox }} />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
