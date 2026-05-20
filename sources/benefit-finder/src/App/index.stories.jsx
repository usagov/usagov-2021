import App from './index.jsx'
import content from '@api/mock-data/current.js'

const { data } = JSON.parse(content)

export default {
  component: App,
  tags: ['autodocs'],
  args: {
    testAppContent: data,
  },
  parameters: {
    layout: 'fullscreen',
  },
}

export const Primary = {}
