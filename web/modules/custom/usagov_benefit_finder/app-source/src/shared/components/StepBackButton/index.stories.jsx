import StepBackButton from './index.jsx'

export default {
  component: StepBackButton,
  tags: ['autodocs'],
  args: {
    setCurrent: currentIndex =>
      currentIndex === 0
        ? alert('back to index 0')
        : alert(`back to index ${currentIndex}`),
    currentIndex: 0,
  },
}

export const Primary = {}
