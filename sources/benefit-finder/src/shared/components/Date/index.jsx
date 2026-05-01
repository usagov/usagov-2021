import PropTypes from 'prop-types'
import './_index.scss'

/**
 * a parse our date object
 * @component
 * @param {func} onChange - inherited change handler
 * @param {object} value - inherited state values
 * @param {object} ui - inherited ui object values
 * @param {string} errorMessage - inherited ui string value for custom error overrides
 * @param {string} parentLegend - inherited ui string values
 * @param {string} id - inherited string value for id specificity
 * @param {array} invalid - inherited boolean value to manage error state

 * @return {Date} returns a standard format Date ie 1995-12-17T03:24:00
 */
const Date = ({
  onChange,
  value,
  ui,
  parentLegend,
  id,
  invalid,
  errorMessage,
}) => {
  const { date, select, errorText } = ui
  const { labelDay, labelMonth, labelYear, monthOptions } = date
  const { dateDefaultValue } = select
  const { suffix, prefix } = errorText

  // Note: when we break each input into functional components they trigger unwanted rerenders

  const handleInvalid = (invalidArray, id) => {
    return invalidArray && invalidArray.map(el => el.id === id).includes(true)
  }

  const errorMessages = {
    month: errorMessage
      ? `${errorMessage} : ${labelMonth.toLowerCase()}`
      : `${prefix} ${parentLegend?.toLowerCase()} ${labelMonth.toLowerCase()} ${suffix}`,
    day: errorMessage
      ? `${errorMessage} : ${labelDay.toLowerCase()}`
      : `${prefix} ${parentLegend?.toLowerCase()} ${labelDay.toLowerCase()} ${suffix}`,
    year: errorMessage
      ? `${errorMessage} : ${labelYear.toLowerCase()}`
      : `${prefix} ${parentLegend?.toLowerCase()} ${labelYear.toLowerCase()} ${suffix}`,
  }

  return (
    <>
      <ul className="add-list-reset">
        {handleInvalid(invalid, `${id}_month`) && (
          <li
            id={`month-error-description-${id}`}
            data-testid="month-error-description"
            className="bf-error-detail"
            aria-live="assertive"
          >
            {errorMessages.month}
          </li>
        )}
        {handleInvalid(invalid, `${id}_day`) && (
          <li
            id={`day-error-description-${id}`}
            data-testid="day-error-description"
            className="bf-error-detail"
            aria-live="assertive"
          >
            {errorMessages.day}
          </li>
        )}
        {handleInvalid(invalid, `${id}_year`) && (
          <li
            id={`year-error-description-${id}`}
            data-testid="year-error-description"
            className="bf-error-detail"
            aria-live="assertive"
          >
            {errorMessages.year}
          </li>
        )}
      </ul>
      <div
        id={`bf-usa-memorable-date-${id}`}
        className="bf-usa-memorable-date usa-memorable-date"
        data-testid="bf-usa-memorable-date"
      >
        <div className="bf-usa-form-group usa-form-group bf-usa-form-group--month usa-form-group--month bf-usa-form-group--select usa-form-group--select">
          <label className="bf-usa-label usa-label" htmlFor={`${id}_month`}>
            {labelMonth}
          </label>
          <div id={`${id}-month-description`} className="usa-sr-only">
            Select a month from the list
          </div>
          <select
            className={`bf-usa-select usa-select ${handleInvalid(invalid, `${id}_month`) ? 'usa-input--error' : ''}`}
            id={`${id}_month`}
            data-testid={`${id}_month`}
            name={`${id}_month`}
            aria-describedby={`${id}-month-description`}
            value={(value && value.month) || ''}
            onChange={onChange}
            aria-invalid={handleInvalid(invalid, `${id}_month`)}
            data-errormessage={errorMessages.month}
            aria-errormessage={`month-error-description-${id}`}
            data-datetype="month"
          >
            <option value="" key="default">
              {dateDefaultValue}
            </option>
            {monthOptions.map((option, i) => (
              <option value={i} key={`${option.label}-${i}`}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
        <div className="bf-usa-form-group usa-form-group bf-usa-form-group--day usa-form-group--day">
          <label className="bf-usa-label usa-label" htmlFor={`${id}_day`}>
            {labelDay}
          </label>
          <div id={`${id}-day-description`} className="usa-sr-only">
            Enter two numerals for day
          </div>
          <input
            className={`bf-usa-input usa-input ${handleInvalid(invalid, `${id}_day`) ? 'usa-input--error' : ''}`}
            aria-describedby={`${id}-day-description`}
            id={`${id}_day`}
            data-testid={`${id}_day`}
            name={`${id}_day`}
            inputMode="numeric"
            value={(value && value.day) || ''}
            onChange={onChange}
            aria-invalid={handleInvalid(invalid, `${id}_day`)}
            data-errormessage={errorMessages.day}
            aria-errormessage={`day-error-description-${id}`}
            data-datetype="day"
          />
        </div>
        <div className="bf-usa-form-group usa-form-group bf-usa-form-group--year usa-form-group--year">
          <label className="bf-usa-label usa-label" htmlFor={`${id}_year`}>
            {labelYear}
          </label>
          <div id={`${id}-year-description`} className="usa-sr-only">
            Enter four numerals for year
          </div>
          <input
            className={`bf-usa-input usa-input ${handleInvalid(invalid, `${id}_year`) ? 'usa-input--error' : ''}`}
            aria-describedby={`${id}-year-description`}
            id={`${id}_year`}
            data-testid={`${id}_year`}
            name={`${id}_year`}
            inputMode="numeric"
            value={(value && value.year) || ''}
            onChange={onChange}
            aria-invalid={handleInvalid(invalid, `${id}_year`)}
            data-errormessage={errorMessages.year}
            aria-errormessage={`year-error-description-${id}`}
            data-datetype="year"
          />
        </div>
      </div>
    </>
  )
}

Date.propTypes = {
  onChange: PropTypes.func,
  value: PropTypes.object,
  ui: PropTypes.object,
  id: PropTypes.string,
  invalid: PropTypes.oneOfType([PropTypes.bool, PropTypes.array]),
}

export default Date
