import { render } from '@testing-library/react'
import Form from '../index.jsx'

describe('Form', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Form />)
    expect(asFragment()).toMatchSnapshot()
  })
})
