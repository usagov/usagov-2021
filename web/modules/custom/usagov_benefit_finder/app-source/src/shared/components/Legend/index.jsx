import PropTypes from 'prop-types'
import './_index.scss'

/**
 * a functional component that renders a string
 * @component
 * @param {React.ReactNode} children - inherited children
 * @return {html} returns a semantic legend element
 */
const Legend = ({ children }) => {
  return <legend className="bf-legend usa-legend">{children}</legend>
}

Legend.propTypes = {
  children: PropTypes.node,
}

export default Legend
