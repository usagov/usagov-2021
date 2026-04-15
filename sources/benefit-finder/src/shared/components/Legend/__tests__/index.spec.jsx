import { render } from '@testing-library/react'
import Legend from '../index.jsx'

describe('Legend', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Legend />)
    expect(asFragment()).toMatchSnapshot()
  })
})
