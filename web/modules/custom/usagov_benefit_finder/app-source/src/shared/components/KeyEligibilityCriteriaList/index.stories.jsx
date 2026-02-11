import KeyEligibilityCriteriaList from './index.jsx'
import content from '@api/mock-data/current.js'
import * as en from '@locales/en/en.json'

const { resultsView } = en

const { data } = JSON.parse(content)
const { benefits } = data
const b = benefits[0].benefit.eligibility
const initialEligibilityLength = benefits[0].benefit.initialEligibilityLength

export default {
  component: KeyEligibilityCriteriaList,
  tags: ['autodocs'],
  args: {
    data: b,
    initialEligibilityLength,
    ui: resultsView.benefitAccordion,
  },
}

export const Primary = {}
