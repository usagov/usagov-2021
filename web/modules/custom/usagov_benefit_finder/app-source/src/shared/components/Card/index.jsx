import PropTypes from 'prop-types'
import { Heading, Icon } from '@components'
import { useHandleClassName } from '@hooks'
import { createMarkup } from '@utils'
import './_index.scss'

/**
 * a functional component that renders a usa-card component
 * @component
 * @param {string} className - inherited class(es)
 * @param {string} href - location
 * @param {string} title - content
 * @param {string} body - content
 * @param {string} cta - content
 * @param {boolean} noCarrot - adds a decorative carrot to the link
 * @param {string} carrotType - one of ['small-carrot', 'big-carrot']
 * @param {string} icon - string name of icon, see <Icon /> component source
 * @return {html} returns a semantic html list element
 */
const Card = ({
  className,
  title,
  body,
  cta,
  href,
  noCarrot,
  carrotType,
  icon,
  ...props
}) => {
  const defaultClasses = icon !== undefined ? ['bf-card-icon'] : ''
  const utilityClasses = ['add-list-reset']
  const handleCarrot =
    noCarrot === true ? null : (
      <Icon type={carrotType} color="#162E51" aria-hidden="true" />
    )
  const handleIcon =
    icon === undefined ? null : (
      <Icon type={icon} className="bf-relative-icon" aria-hidden="true" />
    )

  return (
    <li
      className={useHandleClassName({
        className,
        defaultClasses,
        utilityClasses,
      })}
      key={`${title}`}
      {...props}
    >
      <a className="bf-usa-card__container usa-card__container" href={href}>
        {handleIcon}
        <div className="bf-usa-card__header usa-card__header">
          <Heading
            className="bf-usa-card__heading usa-card__heading"
            headingLevel={3}
          >
            {title}
          </Heading>
        </div>
        <div
          className="bf-usa-card__body usa-card__body"
          dangerouslySetInnerHTML={createMarkup(body)}
        />
        <div className="bf-usa-card__cta usa-card__cta">{cta}</div>
        {handleCarrot}
      </a>
    </li>
  )
}

Card.propTypes = {
  className: PropTypes.string,
  title: PropTypes.string,
  body: PropTypes.string,
  href: PropTypes.string,
  noCarrot: PropTypes.bool,
  carrotType: PropTypes.string,
  icon: PropTypes.string,
}

export default Card
