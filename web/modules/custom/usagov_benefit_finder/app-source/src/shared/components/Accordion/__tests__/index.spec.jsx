import { render } from '@testing-library/react'
import Accordion from '../index.jsx'

const args = {
  id: 'first-amendment',
  heading: 'First Amendment',
  subHeading: 'Likely Eligible',
  children:
    'Congress shall make no law respecting an establishment of religion, or prohibiting the free exercise thereof; or abridging the freedom of speech, or of the press; or the right of the people peaceably to assemble, and to petition the Government for a redress of grievances.',
}

describe('Accordion', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(<Accordion {...args} />)
    expect(asFragment()).toMatchSnapshot()
  })
})
