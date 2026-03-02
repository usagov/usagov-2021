import { render } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import Hint from '../index.jsx'

describe('Hint', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Hint />)
    expect(asFragment()).toMatchSnapshot()
  })
})
