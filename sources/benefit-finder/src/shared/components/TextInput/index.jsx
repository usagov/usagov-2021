import { useHandleClassName } from '@hooks'
import { Label } from '@components'
import PropTypes from 'prop-types'
import './_index.scss'

/**
 * a functional component that renders a field for text input
 * @component
 * @param {string} id - unique Identifier
 * @param {string} className - inherited classes
 * @param {string} label - string value for input label
 * @param {boolean} textarea - conditional to render textarea as return
 * @return {html} returns a semantic textarea or input
 */

const TextInput = ({ id, className, label, textarea }) => {
  const defaultClasses = !textarea
    ? ['bf-usa-input usa-input']
    : ['bf-usa-textarea usa-textarea']

  /**
   * a functional component to create a textarea element.
   * @function
   * @param {props} object - inherited properties
   * @return {html} returns a semantic textarea
   */
  const TextArea = props => <textarea {...props} />

  /**
   * a functional component to create a input element.
   * @function
   * @param {props} object - inherited properties
   * @return {html} returns a semantic textarea
   */
  const PrimaryInput = props => <input {...props} type="text" />

  const Input = props =>
    !textarea ? <PrimaryInput {...props} /> : <TextArea {...props} />

  return (
    <>
      <Label htmlFor={id} label={label} />
      <Input
        className={useHandleClassName({
          className,
          defaultClasses,
        })}
        id={id}
        name={id}
      />
    </>
  )
}

TextInput.propTypes = {
  id: PropTypes.string,
  className: PropTypes.string,
  label: PropTypes.string,
  textarea: PropTypes.bool,
}

export default TextInput
