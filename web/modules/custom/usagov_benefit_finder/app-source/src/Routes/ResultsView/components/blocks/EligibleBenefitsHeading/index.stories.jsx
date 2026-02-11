import EligibleBenefitsHeading from './index.jsx'
import * as en from '@locales/en/en.json'

const { eligible, summaryBox } = en.resultsView

export default {
  component: EligibleBenefitsHeading,
  tags: ['autodocs'],
  args: {
    ui: { eligible, summaryBox },
  },
}

export const Primary = {}
