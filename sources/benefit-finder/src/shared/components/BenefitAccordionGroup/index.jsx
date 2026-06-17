import PropTypes from 'prop-types'
import { createMarkup, dataLayerUtils } from '@utils'
import {
  Accordion,
  Button,
  Heading,
  KeyEligibilityCriteriaList,
  StepBackButton,
  ObfuscatedLink,
} from '@components'
import './_index.scss'

/**
 * a functional compound component that renders a benefit group accordion
 * @component
 * @param {array} data - our benefits data
 * @param {string} entryKey - which key in the array to target
 * @param {bool} expandAll - determines if we include ExpandAll component
 * @param {bool} isExpandAll - determines if all the accordions in the group are expanded
 * @param {function} setExpandAll - inherited useState function
 * @param {function} notEligibleView - inherited boolean state
 * @param {object} returnToForm - inherited object for more info cta
 * @param {object} ui - inherited ui content
 * @return {html} returns html
 */
const BenefitAccordionGroup = ({
  data,
  entryKey,
  expandAll,
  isExpandAll,
  setExpandAll,
  notEligibleView,
  returnToForm,
  ui,
}) => {
  const { benefitAccordion, benefitAccordionGroup } = ui
  const {
    eligibleStatusLabels,
    agencyPrefix,
    visitLabel,
    unmetLabel,
    sourceIsEnglish,
    backcta,
  } = benefitAccordion
  const { closedState, openState } = benefitAccordionGroup
  const { benefitLink, openAllBenefitAccordions } =
    dataLayerUtils.dataLayerStructure

  /**
   * a function that returns the string value of our expanded action
   * @function
   * @return {string} returns label for our button
   * @return {string} returns label for our button
   */
  const handleExpandIcon = isExpandAll ? `${openState} -` : `${closedState} +`

  // handle dataLayer
  /**
   * a function that pushes dataLayer events when the user clicks the link of that benefit
   * @function
   */
  const handleBenefitLinkClick = title => {
    dataLayerUtils.dataLayerPush(window, {
      event: benefitLink.event,
      bfData: {
        benefitTitle: title,
      },
    })
  }

  /**
   * a function that handles expanded state and pushes dataLayer events when the user clicks the "open all" action
   * @function
   * @prop {boolean} isExpandAll true or false
   */
  const handleExpandAll = isExpandAll => {
    setExpandAll(!isExpandAll)
    dataLayerUtils.dataLayerPush(
      window,
      {
        event: openAllBenefitAccordions.event,
        bfData: {
          accordionsOpen: !isExpandAll,
        },
      },
      false
    )
  }

  /**
   * a functional component that renders a button and controls the expansion of our accordions
   * @component
   * @return {html} returns html
   */
  const ExpandAll = () => {
    return (
      expandAll && (
        <Button
          className="bf-expand-all"
          aria-label={handleExpandIcon}
          outline
          onClick={() => handleExpandAll(isExpandAll)}
          data-testid="bf-expand-all"
        >
          {handleExpandIcon}
        </Button>
      )
    )
  }

  /**
   * a functional component that list unmet criteria
   * @component
   * @param {array} item - an array of unmet criteria
   * @return {html} returns an unordered list
   */
  const NotEligibleList = ({ items }) => {
    return (
      <div className="bf-unmet-criteria-group">
        <div className="bf-unmet-criteria-title">{unmetLabel}</div>
        <ul className="bf-unmet-criteria-list">
          {items.map((item, index) => {
            const { label } = item
            return (
              <li
                key={`not-eligible-list-${index}`}
                className="bf-unmet-criteria-item"
              >
                {label}
              </li>
            )
          })}
        </ul>
      </div>
    )
  }

  /**
   * a functional component that list unmet criteria
   * @component
   * @param {array} item - an array of unmet criteria
   * @return {html} returns an unordered list
   */
  const MoreInfoList = ({ items }) => {
    return (
      <div className="bf-unmet-criteria-group">
        <div className="bf-unmet-criteria-title">{eligibleStatusLabels[1]}</div>
        <ul className="bf-unmet-criteria-list">
          {items.map((item, index) => {
            const { label } = item
            return (
              <li key={`more-info-${index}`} className="bf-unmet-criteria-item">
                {label}
              </li>
            )
          })}
        </ul>
        <StepBackButton onClick={() => returnToForm()}>
          {backcta?.link}
        </StepBackButton>
      </div>
    )
  }

  return (
    <div className="bf-usa-accordion-group">
      <ExpandAll />
      {data &&
        data.map((item, index) => {
          const {
            agency,
            eligibility,
            SourceLink,
            summary,
            title,
            SourceIsEnglish,
          } = item[entryKey]
          // filter to get benefit criteria matches
          const eligibleBenefits = eligibility.filter(
            item => item.isEligible === true
          )
          // filter to get unmet criteria
          const notEligibleBenefits = eligibility.filter(
            item => item.isEligible === false
          )

          // filter to get criteria without user values to compare with
          const moreInformationNeeded = eligibility.filter(
            item => item.isEligible === undefined
          )
          // determine the eligibility statues based on the benefits length
          // Total Criteria = y
          // Met Criteria = x
          // Not Met Criteria = z

          // Criteria Met Length	Label
          // x === y	"Likely Eligible"
          // z === 0 && x === undefined length > 0 "More Information Needed"
          // Criteria Not Met	Label
          // z > 0	"Not Eligible"
          const eligibleStatus =
            eligibleBenefits.length === eligibility.length
              ? eligibleStatusLabels[0]
              : notEligibleBenefits.length === 0 &&
                  moreInformationNeeded.length > 0
                ? eligibleStatusLabels[1]
                : eligibleStatusLabels[2]

          const handleHidden =
            notEligibleView === false &&
            eligibleStatus !== eligibleStatusLabels[0]
              ? true
              : !!(
                  notEligibleView === true &&
                  eligibleStatus === eligibleStatusLabels[0]
                )

          return (
            <Accordion
              key={`${index}-${title}`}
              id={`${title}`}
              heading={title}
              subHeading={eligibleStatus}
              isExpanded={isExpandAll}
              data-testid="bf-usa-accordion"
              data-test-accordion-title={title}
              hidden={handleHidden}
            >
              <Heading className="bf-usa-detail-title" headingLevel={4}>
                {`${agencyPrefix} ${agency.title}`}
              </Heading>
              <div
                className="bf-usa-detail-summary"
                dangerouslySetInnerHTML={createMarkup(summary)}
              />
              <KeyEligibilityCriteriaList
                className="bf-usa-criteria-list"
                data={eligibleBenefits}
                initialEligibilityLength={eligibility.length}
                ui={benefitAccordion}
              />
              {notEligibleBenefits.length > 0 && (
                <NotEligibleList items={notEligibleBenefits} />
              )}
              {moreInformationNeeded.length > 0 && (
                <>
                  <MoreInfoList items={moreInformationNeeded} />
                </>
              )}
              <div className="bf-usa-accordion-group-cta-wrapper">
                <ObfuscatedLink
                  className="bf-usa-link"
                  href={SourceLink}
                  target="_blank"
                  rel="noopener noreferrer"
                  onClick={() => handleBenefitLinkClick(title)}
                  data-testid="bf-benefit-link"
                  noCarrot
                >
                  {visitLabel} {agency.title}{' '}
                  {sourceIsEnglish && SourceIsEnglish === true
                    ? sourceIsEnglish
                    : ''}
                </ObfuscatedLink>
              </div>
            </Accordion>
          )
        })}
    </div>
  )
}

BenefitAccordionGroup.propTypes = {
  data: PropTypes.array,
  entryKey: PropTypes.string,
  expandAll: PropTypes.bool,
  isExpandAll: PropTypes.bool,
  setExpandAll: PropTypes.func,
  notEligibleView: PropTypes.bool,
  ui: PropTypes.object,
}

export default BenefitAccordionGroup
