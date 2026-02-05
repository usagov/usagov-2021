import { render } from '@testing-library/react'
import PrintButton from '../index.jsx'

describe('PrintButton', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<PrintButton />)
    expect(asFragment()).toMatchSnapshot()
  })
})
