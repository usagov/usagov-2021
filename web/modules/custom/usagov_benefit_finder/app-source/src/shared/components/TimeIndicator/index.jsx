import PropTypes from 'prop-types'
import './_index.scss'

/**
 * a functional component that renders a div and expects a string description of time
 * @component
 * @param {string} description - translated ui values to describe time estimate component
 * @param {string} timeEstimate - The inherited value for time description
 * @return {html} returns a div
 */
const TimeIndicator = ({ timeEstimate, description }) => {
  return (
    <div className="time-indicator">
      {description} {timeEstimate}
    </div>
  )
}

TimeIndicator.propTypes = {
  description: PropTypes.string,
  timeEstimate: PropTypes.string,
}

export default TimeIndicator
