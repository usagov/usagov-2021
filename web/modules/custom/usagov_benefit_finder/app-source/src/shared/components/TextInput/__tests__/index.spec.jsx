import { render } from '@testing-library/react'
import TextInput from '../index.jsx'

describe('TextInput', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<TextInput />)
    expect(asFragment()).toMatchSnapshot()
  })
})
