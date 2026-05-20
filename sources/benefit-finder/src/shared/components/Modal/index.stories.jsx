import { useState } from 'react'
import Modal from './index.jsx'

let modalOpen = false
const setModalOpen = state => (modalOpen = state)

const ModalWrapper = props => {
  const [modalOpen, setModalOpen] = useState(false)

  return <Modal {...props} setModalOpen={setModalOpen} modalOpen={modalOpen} />
}

export default {
  component: ModalWrapper,
  tags: ['autodocs'],
  args: {
    id: 'nav-modal',
    modalHeading: 'Select an option:',
    triggerLabel: 'Continue',
    navItemOneLabel: 'Verify Information',
    navItemTwoLabel: 'View Results',
    navItemOneFunction: () => setModalOpen(!modalOpen),
    navItemTwoFunction: () => setModalOpen(!modalOpen),
    handleCheckRequiredFields: () => true,
    completed: true,
  },
}

export const Primary = args => <ModalWrapper {...args} />
