import Alert from './index.jsx'

export default {
  component: Alert,
  tags: ['autodocs'],
  args: {
    children: 'Visit the agency for full eligibility and requirements.',
  },
}

export const Primary = {}

export const HasError = {
  args: {
    ...Primary.args,
    type: 'error',
    hasError: true,
  },
}
