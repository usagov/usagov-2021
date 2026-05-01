import { useEffect, useState } from 'react'
import { useHandleClassName } from '@hooks'
import { Icon } from '@components'
import Colors from '@styles/colors/_index.js'
import PropTypes from 'prop-types'
import './_index.scss'

/**
 * a functional component that renders a reactive button
 * @component
 * @param {React.ReactNode} children - inherited children
 * @param {string} className - inherited class(es)
 * @param {function} onClick - an inherited function, triggered on a click event
 * @param {boolean} secondary - alternative display styles
 * @param {boolean} disabled - disabled state
 * @param {boolean} unstyled - appear as a link
 * @param {string} type - 'button', 'reset', 'download'
 * @param {string} icon - indicates which icon to include
 * @return {html} returns a semantic html button element
 */

function Button({
  children,
  className,
  onClick,
  secondary,
  outline,
  disabled,
  unstyled,
  type,
  icon,
  ...props
}) {
  const [defaultClasses, setDefaultClasses] = useState(null)
  const style =
    secondary === true
      ? 'secondary'
      : outline === true
        ? 'outline'
        : unstyled === true
          ? 'unstyled'
          : null
  /**
   * a state hook that contains that handles the synthetic hover value
   * @return {boolean} current state of mouseOver/mouseLeave
   */
  const [isHovered, setIsHovered] = useState(false)
  /**
   * a state hook that contains that handles the fill of our svg icons
   * @return {string} current hex value
   */
  const [hoverColor, setHoverColor] = useState()

  useEffect(() => {
    ;(isHovered && secondary) || (isHovered && unstyled)
      ? setHoverColor(Colors.marine)
      : setHoverColor(Colors.popBlue)
  }, [isHovered])

  useEffect(() => {
    switch (style) {
      case 'secondary':
        setDefaultClasses([
          'bf-usa-button',
          'usa-button',
          'bf-usa-button--secondary',
          'usa-button--secondary',
        ])
        break
      case 'outline':
        setDefaultClasses([
          'bf-usa-button',
          'usa-button',
          'bf-usa-button--outline',
          'usa-button--outline',
        ])
        break
      case 'unstyled':
        setDefaultClasses([
          'bf-usa-button',
          'usa-button',
          'bf-usa-button--unstyled',
          'usa-button--unstyled',
        ])
        break
      default:
        setDefaultClasses(['bf-usa-button', 'usa-button'])
    }
  }, [style, secondary, unstyled])

  return (
    <button
      onClick={disabled ? null : onClick}
      type={type || 'button'}
      disabled={disabled}
      aria-disabled={disabled}
      className={useHandleClassName({
        className,
        defaultClasses,
      })}
      onMouseOver={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      data-testid={props['data-testid']}
      id={props.id}
      data-test="button"
    >
      {icon && <Icon type={icon} color={hoverColor} aria-hidden="true" />}
      {children}
    </button>
  )
}

Button.propTypes = {
  children: PropTypes.node,
  className: PropTypes.string,
  onClick: PropTypes.func,
  secondary: PropTypes.bool,
  disabled: PropTypes.bool,
  unstyled: PropTypes.bool,
  type: PropTypes.oneOf(['button', 'reset', 'download']),
  icon: PropTypes.string,
}

export default Button
