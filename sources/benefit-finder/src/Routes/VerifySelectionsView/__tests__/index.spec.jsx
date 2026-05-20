import { render } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import VerifySelectionsView from '../index.jsx'
import * as en from '@locales/en/en.json'

beforeAll(() => {
  // handle window.scrollTo
  const noop = () => {}
  Object.defineProperty(window, 'scrollTo', { value: noop, writable: true })
})

describe('VerifySelectionsView', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <VerifySelectionsView ui={en.verifySelectionsView} />,
      { wrapper: BrowserRouter }
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
