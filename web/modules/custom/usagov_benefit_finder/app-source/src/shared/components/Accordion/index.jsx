import { useEffect, useState } from 'react'
import accordion from '@uswds/uswds/js/usa-accordion'
import { Icon } from '@components'
import { dataLayerUtils } from '@utils'
import PropTypes from 'prop-types'
import './_index.scss'

/**
 * a functional component that renders a usa accordion
 * @component
 * @param {string} id - inherited id
 * @param {string} heading - title value
 * @param {string} subHeading - eligibleStatus value
 * @param {React.ReactNode} children - inherited children
 * @param {bool} isExpanded - sets the open state of an accordion
 * @return {html} returns a semantic html button element
 */
const Accordion = ({
  id,
  heading,
  subHeading,
  children,
  isExpanded,
  hidden,
  ...props
}) => {
  useEffect(() => {
    accordion.on()

    // remove event listeners when the component un-mounts.
    return () => {
      accordion.off()
    }
  })
  /**
   * a hook that handles our open state of the accordion
   * @function
   * @return {boolean} returns true or false
   */
  const [isOpen, setOpen] = useState(false)

  const { benefitAccordion } = dataLayerUtils.dataLayerStructure

  // handle dataLayer
  const handleOpenClose = isOpen => {
    setOpen(isOpen)
    isOpen === true &&
      dataLayerUtils.dataLayerPush(
        window,
        {
          event: benefitAccordion.event,
          bfData: {
            benefitTitle: heading,
          },
        },
        false
      )
  }

  // handle expand all
  useEffect(() => {
    setOpen(isExpanded)
  }, [isExpanded])

  /**
   * a handler that returns the proper icon
   * @function
   * @param {React.ReactNode} children - inherited children
   * @return {html} returns a semantic img element with proper svg
   */
  const handleIcon = () =>
    !isOpen ? (
      <Icon type="open" aria-hidden="true" />
    ) : (
      <Icon type="close" aria-hidden="true" />
    )

  const handleAriaControl = id => {
    return id.replace(/ +/g, '-').toLowerCase()
  }

  return (
    <div className="bf-usa-accordion usa-accordion" {...props} hidden={hidden}>
      <h4
        className="bf-usa-accordion__heading usa-accordion__heading"
        data-testid="accordion-heading"
      >
        <button
          type="button"
          className="bf-usa-accordion__button usa-accordion__button"
          aria-expanded={isOpen || false}
          aria-controls={id && handleAriaControl(id)}
          onClick={() => handleOpenClose(!isOpen)}
        >
          <span className="bf-accordion-heading">{heading}</span>
          <br />
          <span className="bf-accordion-sub-heading">{subHeading}</span>
          {handleIcon()}
        </button>
      </h4>
      <div
        id={id && handleAriaControl(id)}
        className="bf-usa-accordion__content usa-accordion__content usa-prose"
        hidden={isOpen || true}
      >
        <div>{children}</div>
      </div>
    </div>
  )
}

Accordion.propTypes = {
  id: PropTypes.string,
  heading: PropTypes.string,
  subHeading: PropTypes.string,
  description: PropTypes.string,
  children: PropTypes.node,
}

export default Accordion
