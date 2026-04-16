import { render } from '@testing-library/react'
import Date from '../index.jsx'
import * as en from '@locales/en/en.json'

const fieldSetId = 'applicant_date_of_birth_0'

describe('Date', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <Date id={fieldSetId} ui={en} onChange={() => {}} />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
