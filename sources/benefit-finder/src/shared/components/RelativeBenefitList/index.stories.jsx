import RelativeBenefitList from './index.jsx'
import content from '@api/mock-data/current.js'

const { data } = JSON.parse(content)

export default {
  component: RelativeBenefitList,
  tags: ['autodocs'],
  args: { data: data.lifeEventForm.relevantBenefits, carrotType: 'carrot' },
}

export const Primary = {}
