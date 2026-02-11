import ZeroBenefitsHeading from './index.jsx'
import * as en from '@locales/en/en.json'

const { zeroBenefits } = en.resultsView

export default {
  component: ZeroBenefitsHeading,
  tags: ['autodocs'],
  args: {
    ui: zeroBenefits,
    notEligibleView: false,
  },
}

export const Primary = {}
