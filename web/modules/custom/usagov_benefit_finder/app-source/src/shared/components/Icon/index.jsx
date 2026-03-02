import PropTypes from 'prop-types'
import * as Icons from './index_icons'
import './_index.scss'

/**
 * a functional component that renders a svg icon
 * @component
 * @param {string} type - string kabob value of component name
 * @param {string} color - hex or value
 * @param {any} props - inherited props
 * @return {html} returns a semantic inline element with svg inside
 */
const Icon = ({ type, color, ...props }) => {
  let icon

  switch (type) {
    case 'all_benefits':
      icon = <Icons.AllBenefits />
      break
    case 'carrot-solid':
      icon = <Icons.CarrotSolid color={color} />
      break
    case 'carrot':
      icon = <Icons.Carrot color={color} />
      break
    case 'close':
      icon = <Icons.Close />
      break
    case 'death':
      icon = <Icons.Death />
      break
    case 'disability':
      icon = <Icons.Disability />
      break
    case 'email':
      icon = <Icons.Email color={color} />
      break
    case 'green-check':
      icon = <Icons.GreenCheck color={color} />
      break
    case 'info':
      icon = <Icons.Info color={color} />
      break
    case 'open':
      icon = <Icons.Open />
      break
    case 'modal-close':
      icon = <Icons.ModalClose color={color} />
      break
    case 'retirement':
      icon = <Icons.Retirement />
      break
    case 'share':
      icon = <Icons.Share color={color} />
      break
    default:
      icon = null
  }
  return (
    <i {...props} data-testid={`icon-${type}`}>
      {icon}
    </i>
  )
}

Icon.propTypes = {
  type: PropTypes.string,
  color: PropTypes.string,
  props: PropTypes.any,
}

export default Icon
