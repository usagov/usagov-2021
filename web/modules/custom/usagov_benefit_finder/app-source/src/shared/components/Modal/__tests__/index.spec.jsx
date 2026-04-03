import { render } from '@testing-library/react'
import Modal from '../index.jsx'

describe('Modal', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<div id="benefit-finder" />)
    render(<Modal />)
    expect(asFragment()).toMatchSnapshot()
  })
})
