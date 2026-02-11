import Fieldset from './index.jsx'
import TextInput from '@components/TextInput'
import * as en from '@locales/en/en.json'

const ui = en.errorText

export default {
  component: Fieldset,
  tags: ['autodocs'],
  args: {
    children: <TextInput label="Text input label" />,
    legend: 'Legend',
    hint: 'Hint',
    required: false,
    ui,
  },
}

export const Primary = {}
export const Required = {
  args: {
    ...Primary.args,
    required: true,
  },
}
export const Error = {
  args: {
    ...Primary.args,
    required: true,
    invalid: true,
  },
}
