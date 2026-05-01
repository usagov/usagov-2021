import { render } from '@testing-library/react'
import Banner from '../index.jsx'

describe('Banner', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Banner />)
    expect(asFragment()).toMatchSnapshot()
  })
})
