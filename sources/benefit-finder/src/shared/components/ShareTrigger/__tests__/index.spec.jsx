import { render } from '@testing-library/react'
import ShareTrigger from '../index.jsx'

describe('Share', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<ShareTrigger />)
    expect(asFragment()).toMatchSnapshot()
  })
})
