import StepIndicator from './index.jsx'
import content from '@api/mock-data/current.js'

const { data } = JSON.parse(content)
const { lifeEventForm } = data

export default {
  component: StepIndicator,
  tags: ['autodocs'],
  args: {
    data: lifeEventForm.sectionsEligibilityCriteria,
    current: 0,
  },
}

export const Primary = {}

export const NoHeadings = {
  args: {
    ...Primary,
    noHeadings: true,
  },
}
