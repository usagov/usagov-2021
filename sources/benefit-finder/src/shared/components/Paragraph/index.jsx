import { useHandleClassName } from '@hooks'
import PropTypes from 'prop-types'
import './_index.scss'

const Paragraph = ({ children, className, weight }) => {
  const defaultClasses = [`${weight}`]
  return (
    <p className={useHandleClassName({ className, defaultClasses })}>
      {children}
    </p>
  )
}

Paragraph.propTypes = {
  children: PropTypes.node || PropTypes.string,
  weight: PropTypes.oneOf(['regular', 'bold', 'extrabold', 'light', 'thin']),
}

export default Paragraph
