import { render } from '@testing-library/react'
import TimeIndicator from '../index.jsx'

describe('TimeIndicator', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<TimeIndicator />)
    expect(asFragment()).toMatchSnapshot()
  })
})
