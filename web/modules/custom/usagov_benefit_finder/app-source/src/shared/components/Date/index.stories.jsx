import Date from './index.jsx'
import * as en from '@locales/en/en.json'

export default {
  component: Date,
  tags: ['autodocs'],
  args: {
    ui: { ...en },
    value: { month: '2', day: '22', year: '1977' },
  },
}

export const Primary = {}
