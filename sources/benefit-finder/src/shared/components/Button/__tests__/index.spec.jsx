import { render } from '@testing-library/react'
import Button from '../index.jsx'

describe('Button', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Button />)
    expect(asFragment()).toMatchSnapshot()
  })
})
