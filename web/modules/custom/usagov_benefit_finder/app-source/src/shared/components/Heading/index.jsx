import PropTypes from 'prop-types'
import { useHandleClassName } from '@hooks'
import './_index.scss'

/**
 * a functional component that renders a semantic heading element
 * @component
 * @param {React.ReactNode} children - inherited children
 * @param {string} className - inherited class names
 * @param {number} headingLevel - inherited class(es)
 * @return {html} returns a semantic html anchor element
 */
const Heading = ({ children, className, headingLevel, ...props }) => {
  // pass our default classes
  const defaultClasses = ['']

  // return html as a string for heading element values
  const Tag = `h${headingLevel}`
  return (
    <Tag
      className={useHandleClassName({ className, defaultClasses })}
      id={headingLevel === 1 ? 'skip-to-h1' : null}
      {...props}
      aria-level={headingLevel}
      role="heading"
    >
      {children}
    </Tag>
  )
}

Heading.propTypes = {
  children: PropTypes.oneOfType([PropTypes.string, PropTypes.array]),
  className: PropTypes.string,
  headingLevel: PropTypes.number,
}

export default Heading
