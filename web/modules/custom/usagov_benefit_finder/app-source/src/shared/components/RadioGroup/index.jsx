import PropTypes from 'prop-types'
import { Radio } from '@components'

import './_index.scss'

/**
 * a functional component that renders an input of radio type
 * @component
 * @param {bool} invalid - sets invalid state
 * @param {array} values - radio label values
 * @param {string} fieldSetId - id for our fieldset group
 * @param {func} handleChanged - inherited onChange handler
 * @param {string} criteriaKey - inherited key value
 * @return {html} returns a semantic group for our radio elements
 */

const RadioGroup = ({
  invalid,
  values,
  fieldSetId,
  handleChanged,
  criteriaKey,
  errorMessage,
  legend,
  ui,
}) => {
  const errorText = ui
  const errorMessageValue = errorMessage
    ? `${errorMessage}`
    : `${errorText?.prefix} ${legend && legend.toLowerCase()} ${errorText?.suffix}`

  return (
    <div
      className="bf-radio-group radio-group"
      aria-invalid={invalid}
      data-testid="radio-group"
    >
      {/* map the options */}
      {values &&
        values.map((option, index) => {
          const inputId = `${fieldSetId}_${index}`

          return (
            <Radio
              name={fieldSetId}
              key={inputId}
              id={inputId}
              label={option.value}
              value={option.value}
              checked={option.selected || false}
              onChange={event => {
                handleChanged(event, criteriaKey)
              }}
              data-errormessage={errorMessageValue}
              aria-errormessage={`error-description-${inputId}`}
            />
          )
        })}
    </div>
  )
}

RadioGroup.propTypes = {
  invalid: PropTypes.bool,
  values: PropTypes.array,
  fieldSetId: PropTypes.string,
  handleChanged: PropTypes.func,
  criteriaKey: PropTypes.string,
}

export default RadioGroup
