import TextInput from './index.jsx'

export default {
  component: TextInput,
  tags: ['autodocs'],
  args: {
    id: 'input-type-text',
    label: 'Text input label',
    textarea: false,
  },
}

export const Primary = {}

export const Error = {
  args: {
    ...Primary.args,
    required: true,
    checked: true,
    invalid: true,
    className: 'bf-usa-input--error usa-input--error',
  },
}

export const TextArea = {
  args: {
    ...Primary.args,
    id: 'input-type-textarea',
    label: 'Text area label',
    textarea: true,
  },
}
