import Paragraph from './index.jsx'

export default {
  component: Paragraph,
  tags: ['autodocs'],
  argTypes: {
    children: {
      control: 'text',
    },
    weight: {
      options: ['regular', 'bold', 'extrabold', 'light', 'thin'],
      control: { weight: 'radio' },
    },
  },
  args: {
    children:
      'As we embarked on developing the Benefit Finder platform, our goal was to provide an intuitive and efficient way for users to explore their benefits options. Through meticulous planning, design, and development, we were able to create a user-friendly interface that not only meets but exceeds the expectations of our users.',
  },
}

export const Regular = {
  args: {
    weight: 'regular',
  },
}

export const Bold = {
  args: {
    weight: 'bold',
  },
}

export const ExtraBold = {
  args: {
    weight: 'extrabold',
  },
}

export const Light = {
  args: {
    weight: 'light',
  },
}

export const Thin = {
  args: {
    weight: 'thin',
  },
}
