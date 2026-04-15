import ResultsView from './index.jsx'
import content from '@api/mock-data/current.js'
import * as en from '@locales/en/en.json'

const { resultsView } = en
const { data } = JSON.parse(content)
const { relevantBenefits } = data.lifeEventForm

export default {
  component: ResultsView,
  tags: ['autodocs'],
  args: {
    ui: resultsView,
    relevantBenefits,
  },
}

export const Primary = {}
