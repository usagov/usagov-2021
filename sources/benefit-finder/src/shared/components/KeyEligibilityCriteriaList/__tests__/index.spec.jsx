import { render } from '@testing-library/react'
import KeyEligibilityCriteriaList from '../index.jsx'
import * as en from '@locales/en/en.json'

describe('KeyEligibilityCriteriaList', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <KeyEligibilityCriteriaList ui={en.resultsView.benefitAccordion} />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
