import { render } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import ZeroBenefitsHeading from '../index.jsx'
import * as en from '@locales/en/en.json'
const { resultsView } = en

describe('ZeroBenefitsHeading', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <ZeroBenefitsHeading ui={resultsView.zeroBenefits} />,
      {
        wrapper: BrowserRouter,
      }
    )

    expect(asFragment()).toMatchSnapshot()
  })
})
