import PropTypes from 'prop-types'
import './_index.scss'

const ProcessList = ({ steps }) => {
  return (
    <ol className="bf-usa-process-list usa-process-list">
      {steps &&
        steps.map((step, index) => {
          return (
            <li
              key={`process-item-${index}`}
              className="bf-usa-process-list__item usa-process-list__item"
            >
              <h3 className="bf-usa-process-list__heading usa-process-list__heading">
                {step.title}
              </h3>
            </li>
          )
        })}
    </ol>
  )
}

ProcessList.propTypes = {
  steps: PropTypes.array,
}

export default ProcessList
