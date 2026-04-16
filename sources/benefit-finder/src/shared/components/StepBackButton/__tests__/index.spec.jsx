import { render } from '@testing-library/react'
import StepBackButton from '../index.jsx'

describe('StepBackButton', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<StepBackButton />)
    expect(asFragment()).toMatchSnapshot()
  })
})
