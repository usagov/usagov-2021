import { useContext } from 'react'
import PropTypes from 'prop-types'
import { RouteContext } from '@/App'
import { Heading } from '@components'
import './_index.scss'

const Summary = ({ heading, listItems, cta }) => {
  const ROUTES = useContext(RouteContext)

  const handleReload = e => {
    e.preventDefault()
    window.location.href = `${window.location.origin}${ROUTES.basePath}/${ROUTES.indexPath}`
  }

  return (
    <div
      className="bf-usa-summary-box usa-summary-box"
      role="region"
      aria-labelledby="bf-summary-box-key-information"
    >
      <div className="bf-usa-summary-box__body usa-summary-box__body">
        <Heading
          headingLevel={4}
          className="bf-usa-summary-box__heading usa-summary-box__heading"
          id="bf-summary-box-key-information"
        >
          {heading}
        </Heading>
        <div className="bf-usa-summary-box__text usa-summary-box__text">
          <ul className="bf-usa-list usa-list">
            {listItems &&
              listItems.map((item, i) => (
                <li key={`bf-summary-list-${i}`}>{item.item}</li>
              ))}
            <li>
              {cta?.text}{' '}
              <a
                className="bf-usa-summary-box__link usa-summary-box__link"
                href="#"
                onClick={e => handleReload(e)}
              >
                {cta?.link}
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  )
}

Summary.propTypes = {
  props: PropTypes.any,
}

export default Summary
