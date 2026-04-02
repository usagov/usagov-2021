import { render, screen } from '@testing-library/react'
import BenefitAccordionGroup from '../index.jsx'
import content from '@api/mock-data/current.js'
import * as en from '@locales/en/en.json'
import * as es from '@locales/es/es.json'

const { data } = JSON.parse(content)
const { benefits } = data
const b = benefits[0]
const entryKey = Object.keys(b)

describe('BenefitAccordionGroup', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <BenefitAccordionGroup
        ui={en.resultsView}
        data={[b]}
        entryKey={entryKey[0]}
      />
    )
    expect(asFragment()).toMatchSnapshot()
  })

  it('renders (en inglés) when flagged as TRUE', () => {
    b.benefit.SourceIsEnglish = true // ensure true
    render(
      <BenefitAccordionGroup
        ui={es.resultsView}
        data={[b]}
        entryKey={entryKey[0]}
      />
    )
    const content = screen.queryByText(/en inglés/)
    expect(content).toBeInTheDocument()
  })

  it('does not render (en inglés) when flagged as FALSE', () => {
    b.benefit.SourceIsEnglish = false // ensure false
    render(
      <BenefitAccordionGroup
        ui={es.resultsView}
        data={[b]}
        entryKey={entryKey[0]}
      />
    )
    const content = screen.queryByText(/en inglés/)
    expect(content).not.toBeInTheDocument()
  })
})
