import { Hint, Legend } from '@components'
import PropTypes from 'prop-types'
import { useHandleClassName } from '@hooks'

import './_index.scss'

/**
 * a functional component that renders a string
 * @component
 * @param {React.ReactNode} children - inherited children
 * @param {string} legend - passed to Legend component
 * @param {bool} required - inherited from data
 * @param {node} alertRef - inherited reference
 * @param {object} requiredLabel - passed to Legend component
 * @param {boolean} hidden - inherited from data
 * @param {string} hint - passed to Hint component
 * @param {string} className - inherited classes
 * @param {string} id - inherited id value
 * @param {bool} invalid - inherited boolean value for valid or invalid state
 * @param {object} ui - inherited locale based ui values
 * @return {html} returns a div
 */
const Fieldset = ({
  children,
  legend,
  required,
  alertRef,
  requiredLabel,
  hidden,
  errorMessage,
  hint,
  className,
  id,
  invalid,
  ui,
}) => {
  const handleHidden = hidden !== undefined && hidden ? ['display-none'] : ''
  const defaultClasses = [
    `bf-usa-fieldset usa-fieldset ${required === true ? 'required-field' : ''} ${invalid === true ? 'usa-input--error' : ''}`,
  ]
  const utilityClasses = handleHidden
  const { prefix, suffix } = ui

  /**
   * a functional component that renders a legend with a required hint
   * @component
   * @param {React.ReactNode} children - inherited children
   * @param {string} legend - passed to Legend component
   * @param {object} requiredLabel - passed to Hint component
   * @return {html} returns a div
   */
  const RequiredFlag = () => (
    <>
      <Legend>
        {legend}
        <Hint requiredLabel={requiredLabel} />
      </Legend>
    </>
  )

  // determine if we need to include a required hint
  const handleRequired =
    required === false ? <Legend>{legend}</Legend> : RequiredFlag()

  const handleErrorMessage = errorMessage
    ? `${errorMessage}`
    : `${prefix} ${legend && legend.toLowerCase().replace('?', '')} ${suffix}`

  return (
    <div className="bf-fieldset-wrapper">
      <fieldset
        className={useHandleClassName({
          className,
          defaultClasses,
          utilityClasses,
        })}
        ref={alertRef}
        required={required === true}
        id={id}
        data-errormessage={handleErrorMessage}
        hidden={hidden}
        aria-hidden={hidden}
        data-testid="fieldset"
      >
        {legend && handleRequired}
        {invalid === true && (
          <div
            id={`error-description-${id}`}
            data-testid="error-description"
            className="bf-error-detail"
            aria-live="assertive"
          >
            {handleErrorMessage}
          </div>
        )}
        {hint && <div className="bf-hint">{hint}</div>}
        {children}
      </fieldset>
    </div>
  )
}

Fieldset.propTypes = {
  children: PropTypes.node,
  legend: PropTypes.string,
  alertRef: PropTypes.any,
  requiredLabel: PropTypes.object,
  hidden: PropTypes.bool,
  hint: PropTypes.string,
  className: PropTypes.string,
  invalid: PropTypes.bool,
  ui: PropTypes.object,
}

export default Fieldset
