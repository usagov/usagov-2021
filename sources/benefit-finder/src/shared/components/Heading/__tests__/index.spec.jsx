import { render } from '@testing-library/react'
import Heading from '../index.jsx'

describe('Heading', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Heading headingLevel={1} />)
    expect(asFragment()).toMatchSnapshot()
  })
})
