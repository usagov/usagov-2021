import NoticesList from './index.jsx'
import * as en from '@locales/en/en.json'

const { intro } = en

export default {
  component: NoticesList,
  tags: ['autodocs'],
  args: {
    data: intro.notices.list,
  },
}

export const Primary = {}
