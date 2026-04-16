import { render } from '@testing-library/react'
import Radio from '../index.jsx'

describe('Radio', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Radio />)
    expect(asFragment()).toMatchSnapshot()
  })
})
