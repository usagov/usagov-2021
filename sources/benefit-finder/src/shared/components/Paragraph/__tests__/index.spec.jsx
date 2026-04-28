import { render } from '@testing-library/react'
import Paragraph from '../index.jsx'

describe('Paragraph', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Paragraph />)
    expect(asFragment()).toMatchSnapshot()
  })
})
