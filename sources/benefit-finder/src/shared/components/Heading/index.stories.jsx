import Heading from './index.jsx'

export default {
  component: Heading,
  tags: ['autodocs'],
  argTypes: {
    children: {
      control: 'text',
    },
    headingLevel: {
      control: 'number',
      defaultValue: 2,
    },
  },
  args: {
    children: 'Heading text',
    headingLevel: 1,
  },
}

export const Default = {}

export const Heading1 = {
  args: {
    children: 'H1 Heading 1 -  Sample',
    headingLevel: 1,
  },
}

export const Heading2 = {
  args: {
    children: 'H2 Heading 2 -  Sample',
    headingLevel: 2,
  },
}

export const Heading3 = {
  args: {
    children: 'H3 Heading 3 -  Sample',
    headingLevel: 3,
  },
}
