import { render } from '@testing-library/react'
import Card from '../index.jsx'

describe('Card', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Card />)
    expect(asFragment()).toMatchSnapshot()
  })
})
