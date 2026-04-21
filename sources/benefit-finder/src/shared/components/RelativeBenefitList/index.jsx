import PropTypes from 'prop-types'
import { createMarkup } from '@utils'

import './_index.scss'

/**
 * a functional component that renders a group of linked elements
 * @component
 * @param {array} data - passed benefits data
 * @return {html} returns a grid of linked elements
 */
const RelativeBenefitList = ({ data }) => {
  return (
    // markup is cloned from usagov, styles are inherited
    <div id="benefit-finder__main" className="benefit-finder__main">
      <div className="life-events-grid">
        {data &&
          data.map((item, i) => {
            const { title, searchTitle, link, body, lifeEventId } =
              item.lifeEvent
            const trimmedLifeEventId = lifeEventId.replace('es_', '')
            const lang = lifeEventId.includes('es_') ? 'es' : 'en'

            return (
              <div
                data-testid={`benefit-finder-icon--${trimmedLifeEventId}`}
                className={`life-events-item benefit-finder-icon--${trimmedLifeEventId}`}
                key={`${title}-${i}`}
              >
                <div className="life-events-item-content">
                  <h3>
                    <a
                      href={link}
                      data-testid={trimmedLifeEventId}
                      hrefLang={lang}
                    >
                      {searchTitle || title}
                    </a>
                  </h3>
                  <div dangerouslySetInnerHTML={createMarkup(body)} />
                </div>
              </div>
            )
          })}
      </div>
    </div>
  )
}

RelativeBenefitList.propTypes = {
  data: PropTypes.array,
  carrotType: PropTypes.string,
}

export default RelativeBenefitList
