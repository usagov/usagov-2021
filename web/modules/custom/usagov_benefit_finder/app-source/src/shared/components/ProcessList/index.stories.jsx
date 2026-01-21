import ProcessList from './index.jsx'

const steps = [
  { title: 'Complete questions' },
  { title: 'Review results' },
  { title: 'Find out how to apply' },
]

export default {
  component: ProcessList,
  tags: ['autodocs'],
  args: {
    steps,
  },
}

export const Primary = {}
