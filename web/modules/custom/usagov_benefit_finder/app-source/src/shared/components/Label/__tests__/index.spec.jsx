import { render } from '@testing-library/react'
import Label from '../index.jsx'

describe('Label', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Label />)
    expect(asFragment()).toMatchSnapshot()
  })
})
