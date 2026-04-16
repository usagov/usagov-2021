import { render } from '@testing-library/react'
import ProcessList from '../index.jsx'

describe('ProcessList', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<ProcessList />)
    expect(asFragment()).toMatchSnapshot()
  })
})
