import { render } from '@testing-library/react'
import RadioGroup from '../index.jsx'

describe('RadioGroup', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<RadioGroup />)
    expect(asFragment()).toMatchSnapshot()
  })
})
