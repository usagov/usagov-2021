import { render } from '@testing-library/react'
import Fieldset from '../index.jsx'
import * as en from '@locales/en/en.json'

const fieldSetId = 'applicant_date_of_birth_0'

describe('Fieldset', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <Fieldset ui={en.errorText} legend="Legend" hint="hint" />
    )
    expect(asFragment()).toMatchSnapshot()
  })
  it('renders a match to the previous error snapshot', () => {
    const { asFragment } = render(
      <Fieldset
        required={true}
        invalid={true}
        ui={en.errorText}
        legend="legend"
        id={fieldSetId}
      />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
