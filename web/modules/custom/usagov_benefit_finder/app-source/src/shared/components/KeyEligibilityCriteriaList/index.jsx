import { Heading, Icon } from '@components'
import PropTypes from 'prop-types'
import { useHandleClassName } from '@hooks'
import './_index.scss'

/**
 * a functional component that renders a list of criteria related to eligibility
 * @component
 * @param {string} className - inherited class names
 * @param {array} data - contains our criteria
 * @param {string} initialEligibilityLength - location
 * @return {html} returns a group with a heading and an unordered list
 */
const KeyEligibilityCriteriaList = ({
  className,
  data,
  initialEligibilityLength,
  ui,
}) => {
  const { benefitSummary, benefitSummaryPrefix, benefitSummaryConjunction } = ui
  const defaultClasses = ['bf-key-eligibility-criteria-group']

  return (
    <div className={useHandleClassName({ className, defaultClasses })}>
      {data && (
        <>
          {' '}
          <Heading
            className="bf-key-eligibility-criteria-heading"
            headingLevel={5}
          >
            {`${benefitSummary}`}
            <span className="bf-key-eligibility-criteria-sub-heading">{` - ${benefitSummaryPrefix} ${data.length} ${benefitSummaryConjunction}
            ${initialEligibilityLength}`}</span>
          </Heading>
          <ul
            className="bf-key-eligibility-criteria-list"
            data-testid="bf-key-eligibility-criteria-list"
          >
            {data.map((item, index) => {
              const { criteriaKey, label } = item
              return (
                <li
                  key={`${criteriaKey}-${index}`}
                  className="bf-usa-list usa-list usa-list--unstyled  bf-usa-list--unstyled bf-key-eligibility-criteria-list-item"
                  data-testid={`${criteriaKey}`}
                >
                  <div aria-hidden="true">
                    <Icon type="green-check" aria-hidden="true" />
                  </div>
                  {label}
                </li>
              )
            })}
          </ul>
        </>
      )}
    </div>
  )
}

KeyEligibilityCriteriaList.propTypes = {
  className: PropTypes.string,
  data: PropTypes.array,
  initialEligibilityLength: PropTypes.number,
}

export default KeyEligibilityCriteriaList
