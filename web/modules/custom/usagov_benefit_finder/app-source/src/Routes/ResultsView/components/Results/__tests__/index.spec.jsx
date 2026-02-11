import { render } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import Results from '../index.jsx'
import * as en from '@locales/en/en.json'

const { resultsView } = en

describe('Results', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Results ui={resultsView} />, {
      wrapper: BrowserRouter,
    })
    expect(asFragment()).toMatchSnapshot()
  })
})
