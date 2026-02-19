import PropTypes from 'prop-types'

import './_index.scss'

/**
 * a functional component that renders a step indicator for our form sections
 * @component
 * @param {array} data - an array of sections
 * @param {boolean} noHeadings - determinate to render headings or not
 * @param {number} current - inherited state, indicates index value
 * @param {boolean} setCurrent - inherited function to manage index value
 * @param {func} handleCheckRequiredFields - inherited handler
 * @return {html} returns markup for a usa step indicator
 */
const StepIndicator = ({
  data,
  noHeadings,
  current,
  setCurrent,
  handleCheckRequiredFields,
}) => {
  /**
   * a functional component that supports a11y for completed steps
   * @component
   * @param {boolean} status - assigns if the step has been completed
   * @param {boolean} noHeadings - determinate to render headings or not
   * @return {html} returns markup for a usa step indicator
   */
  const CompletedSR = ({ completed }) => {
    return (
      <span className="usa-sr-only">
        {completed ? ' completed' : ' not completed'}
      </span>
    )
  }

  /**
   * a functional component that creates our step in the list
   * @component
   * @param {string} heading - associated with the step link
   * @param {number} current - the current index from state
   * @param {function} setCurrent - updates the current index from onClick
   * @param {boolean} completed - state of completion
   * @param {number} index - the index of this item
   * @return {html} returns markup for a usa step indicator segment
   */
  const StepIndicatorSegment = ({ heading, current, completed, index }) => {
    const statusClass = current === index ? '--current' : ''
    return (
      <li
        className={`bf-usa-step-indicator__segment usa-step-indicator__segment bf-usa-step-indicator__segment${statusClass} usa-step-indicator__segment${statusClass} ${
          completed === true
            ? 'bf-usa-step-indicator__segment--complete usa-step-indicator__segment--complete'
            : ''
        }`}
        aria-current={current === index}
        key={`step-indicator-${heading}`}
      >
        <span
          key={`step-indicator-label-${index}`}
          className="bf-usa-step-indicator__segment-label usa-step-indicator__segment-label"
        >
          {!noHeadings && heading}
          <CompletedSR
            key={`step-indicator-sr-${index}`}
            completed={completed}
          />
        </span>
      </li>
    )
  }

  return (
    <div>
      {data && data.length > 0 && (
        <div
          className="bf-usa-step-indicator usa-step-indicator"
          aria-label="progress"
          tabIndex={0}
        >
          <ol className="bf-usa-step-indicator__segments usa-step-indicator__segments">
            {data &&
              data.map((step, i) => {
                const heading = step.section.heading
                const isCompleted = step.completed

                return (
                  <StepIndicatorSegment
                    heading={heading}
                    key={`${heading}-${i}`}
                    index={i}
                    current={current}
                    setCurrent={setCurrent}
                    completed={isCompleted}
                    handleCheckRequiredFields={handleCheckRequiredFields}
                  />
                )
              })}
          </ol>
        </div>
      )}
    </div>
  )
}

StepIndicator.propTypes = {
  data: PropTypes.array,
  noHeadings: PropTypes.bool,
  current: PropTypes.number,
}

export default StepIndicator
