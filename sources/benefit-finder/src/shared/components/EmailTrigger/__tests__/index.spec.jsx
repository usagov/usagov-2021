import { render } from '@testing-library/react'
import EmailTrigger from '../index.jsx'

describe('Email', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<EmailTrigger />)
    expect(asFragment()).toMatchSnapshot()
  })
})
