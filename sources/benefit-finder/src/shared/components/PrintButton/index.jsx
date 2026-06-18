import { Button } from '@components'

/**
 * a functional component that renders a button with window print functionality
 * @component
 * @return {html} returns a semantic html button element with a custom function onClick event
 */

const PrintButton = ({ ui }) => {
  /**
   * a handler that executes our window print functionality
   */
  const handleClick = () => {
    window.print()
  }

  return (
    <Button className="bf-print-button" secondary onClick={handleClick}>
      {ui || 'Print'}
    </Button>
  )
}

export default PrintButton
