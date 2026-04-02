import { useResetElement } from '@hooks'
import PropTypes from 'prop-types'
import { Button } from '@components'

import './_index.scss'

/**
 * a functional component that supports backwards navigation in the step indicator component
 * @component
 * @param {React.ReactNode} children - inherited child react nodes for label
 * @param {function} onClick - inherited click function
 * @return {html} returns markup for a usa unstyled button
 */
const StepBackButton = ({ children, onClick }) => {
  const resetElement = useResetElement()

  const handleStep = () => {
    onClick()
    resetElement.current.focus()
  }

  return (
    <Button
      className="bf-step-back-button"
      data-testid="bf-step-back-button"
      unstyled
      onClick={() => handleStep()}
    >
      {children || 'Back'}
    </Button>
  )
}

StepBackButton.propTypes = {
  children: PropTypes.node,
  currentIndex: PropTypes.number,
}

export default StepBackButton
