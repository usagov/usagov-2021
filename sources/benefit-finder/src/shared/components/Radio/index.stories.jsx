import Radio from './index.jsx'

export default {
  component: Radio,
  tags: ['autodocs'],
  args: {
    label: 'Radio',
    value: 'radio',
    checked: false,
  },
}

export const Primary = {}

export const DefaultChecked = {
  args: {
    checked: true,
  },
}

export const Error = {
  args: {
    ...Primary.args,
    required: true,
    checked: true,
    invalid: true,
    className: 'bf-usa-input--error usa-input--error',
  },
}
