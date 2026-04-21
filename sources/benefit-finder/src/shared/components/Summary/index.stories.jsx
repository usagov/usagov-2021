import Summary from './index.jsx'
import * as en from '@locales/en/en.json'

const { heading, list, cta } = en.resultsView.summaryBox

export default {
  component: Summary,
  tags: ['autodocs'],
  args: {
    heading,
    listItems: list,
    cta,
  },
}

export const Primary = {}
