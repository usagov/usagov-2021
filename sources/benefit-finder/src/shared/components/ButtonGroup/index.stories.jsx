import ButtonGroup from './index.jsx'

export default {
  component: ButtonGroup,
  tags: ['autodocs'],
  args: {
    buttonOne: {
      outline: true,
      children: 'Back',
      onClick: () => alert('navigate back'),
    },
    buttonTwo: {
      secondary: true,
      children: 'Continue',
      onClick: () => alert('navigate forward'),
    },
  },
}

export const Primary = {}
