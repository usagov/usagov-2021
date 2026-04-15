import { render } from '@testing-library/react'
import ObfuscatedLink from '../index.jsx'

describe('ObfuscatedLink', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<ObfuscatedLink />)
    expect(asFragment()).toMatchSnapshot()
  })
})
