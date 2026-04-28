import { render } from '@testing-library/react'
import ButtonGroup from '../index.jsx'

describe('ButtonGroup', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<ButtonGroup />)
    expect(asFragment()).toMatchSnapshot()
  })
})
