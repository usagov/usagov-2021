import { useHandleClassName } from '@hooks'
import { Label } from '@components'
import PropTypes from 'prop-types'

import './_index.scss'

/**
 * a functional component that renders an input of radio type
 * @component
 * @param {string} label - displayed in ui
 * @param {string} value - assigned to value param in html
 * @param {boolean} checked - determines if the radio is selected
 * @param {func} onChange - inherited onChange handler
 * @param {string} classNAme - inherited class strings
 * @return {html} returns a semantic input as type radio element
 */

const Radio = ({
  id,
  label,
  value,
  checked,
  onChange,
  required,
  className,
  name,
}) => {
  const handleRequired = required === true ? ['required-field'] : ''
  const defaultClasses = ['bf-usa-radio__input usa-radio__input']
  const utilityClasses = handleRequired
  return (
    <>
      <div className="bf-usa-radio usa-radio" data-testid="radio">
        <input
          className={useHandleClassName({
            className,
            defaultClasses,
            utilityClasses,
          })}
          type="radio"
          name={name}
          value={value || id}
          checked={checked}
          onChange={onChange}
          id={id}
        />
        <Label
          className="bf-usa-radio__label usa-radio__label"
          htmlFor={id}
          label={label}
        />
      </div>
    </>
  )
}

Radio.propTypes = {
  id: PropTypes.string,
  label: PropTypes.string,
  value: PropTypes.string,
  checked: PropTypes.bool,
  onChange: PropTypes.func,
  className: PropTypes.string,
  name: PropTypes.string,
}

export default Radio
