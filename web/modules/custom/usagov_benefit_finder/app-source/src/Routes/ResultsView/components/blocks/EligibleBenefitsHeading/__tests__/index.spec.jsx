import { render } from '@testing-library/react'
import EligibleBenefitsHeading from '../index.jsx'
import * as en from '@locales/en/en.json'

const { eligible, summaryBox } = en

describe('EligibleBenefitsHeading', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <EligibleBenefitsHeading ui={{ eligible, summaryBox }} />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
