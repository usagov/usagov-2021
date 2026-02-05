import LifeEventSection from './index.jsx'
import content from '@api/mock-data/current.js'
import * as en from '@locales/en/en.json'

const { data } = JSON.parse(content)
const { lifeEventForm } = data

export default {
  component: LifeEventSection,
  tags: ['autodocs'],
  args: {
    ui: { ...en },
    data: lifeEventForm.sectionsEligibilityCriteria,
    step: 1,
  },
}

export const Primary = {}
