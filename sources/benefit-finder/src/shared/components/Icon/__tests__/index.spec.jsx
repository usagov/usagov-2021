import { render } from '@testing-library/react'
import Icon from '../index.jsx'

describe('Icon', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Icon type="close" aria-hidden="true" />)
    expect(asFragment()).toMatchSnapshot()
  })

  it('provides a label and graphic role when provided in attributes', () => {
    const { asFragment } = render(
      <Icon type="info" aria-label="important" role="img" />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
