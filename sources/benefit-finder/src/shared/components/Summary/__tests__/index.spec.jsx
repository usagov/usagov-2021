import { render } from '@testing-library/react'
import Summary from '../index.jsx'
import * as en from '@locales/en/en.json'

const { heading, list, cta } = en.resultsView.summaryBox

describe('Summary', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <Summary heading={heading} listItems={list} cta={cta} />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
