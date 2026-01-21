import { useEffect, useContext } from 'react'
import { useNavigate, useLocation } from 'react-router'
import PropTypes from 'prop-types'
import { RouteContext } from '@/App'
import { parseDate, dataLayerUtils, handleSurvey, a11yTitles } from '@utils'
import { useResetElement, useScrollToAnchor } from '@hooks'
import * as apiCalls from '@api/apiCalls'
import { Heading, Button } from '@components'

import './_index.scss'

/**
 * a functional component that renders a view of the form input state values
 * @component
 * @param {object} ui - translations
 * @param {array} data - inherited state of data in current session
 * @return {html} returns semantic html view for current input values
 */
const VerifySelectionsView = ({ ui, data }) => {
  const { verifySelectionsView, buttonGroup } = ui
  const { verifySelections } = dataLayerUtils.dataLayerStructure
  const locale = apiCalls.GET.Language()
  const dateFormatOptions = {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }
  const navigate = useNavigate()
  const location = useLocation()
  const ROUTES = useContext(RouteContext)
  const resetElement = useResetElement()
  useScrollToAnchor(location)

  /**
   * a functional component that renders markup when no value has been given
   * @component
   * @param {object} item - fieldset object with no selected value
   * @param {number} index - current index of mapped array
   * @return {html} returns html div
   */
  const NoInputGiven = ({ item, index }) => (
    <div className="bf-verify-criteria-value">
      <div
        className="bf-verify-criteria-legend"
        key={`bf-criteria-${item.fieldset.criteriaKey}-${index}`}
      >
        {item.fieldset.legend}
      </div>
      {verifySelectionsView?.noResultsLabel}
    </div>
  )

  /**
   * a functional component that renders markup for fieldset items with selected values
   * @component
   * @param {string} criteriaId - inherited string value
   * @param {string} legend - inherited string value
   * @param {object} selected - selected fieldset object value
   * @return {html} returns html div
   */
  const resultItem = ({ criteriaId, legend, selected }) => {
    return (
      <div className="bf-verify-criteria-value" key={criteriaId}>
        <div className="bf-verify-criteria-legend">{legend}</div>
        {typeof selected?.value === 'object'
          ? `${parseDate(selected.value).toLocaleDateString(
              locale,
              dateFormatOptions
            )}`
          : selected?.value}
      </div>
    )
  }

  /**
   * a functional component that renders markup for fieldset items in the form
   * @component
   * @param {object} item - fieldset object
   * @param {number} index - current index of mapped array
   * @return {html} returns html div
   */
  const Items = ({ item, index }) => {
    return (
      <div>
        {resultItem({
          criteriaId: `criteria-${item.fieldset.criteriaKey}-${index}`,
          legend: item.fieldset.legend,
          selected: apiCalls.GET.SelectedValue(item),
        })}
        {apiCalls.GET.Children(item).map(childItem =>
          apiCalls.GET.SelectedValue(childItem) ? (
            resultItem({
              criteriaId: childItem.fieldset.criteriaKey,
              legend: childItem.fieldset.legend,
              selected: apiCalls.GET.SelectedValue(childItem),
            })
          ) : (
            <NoInputGiven
              item={childItem}
              key={childItem.fieldset.criteriaKey}
            />
          )
        )}
      </div>
    )
  }

  useEffect(() => {
    !location.hash && resetElement.current?.focus()
    !location.hash && window.scrollTo(0, 0)
    a11yTitles(location.pathname, locale)
  }, [location])

  // handle dataLayer
  useEffect(() => {
    dataLayerUtils.dataLayerPush(window, {
      event: verifySelections.event,
      bfData: {
        pageView: verifySelections.bfData.pageView,
        viewTitle: verifySelectionsView?.heading,
      },
    })
    a11yTitles(location.pathname, locale)
  }, [])

  useEffect(() => {
    // hide the survey
    handleSurvey({ hide: true })
  }, [])

  return (
    <div
      className="bf-verify-selections-view"
      data-testid="bf-verify-selections-view"
    >
      <div className="bf-grid-container grid-container">
        <Heading className="bf-section-heading" headingLevel={1}>
          {verifySelectionsView?.heading}
        </Heading>
        <div className="bf-section-wrapper">
          <div className="bf-section-info">
            <div>
              {data &&
                data.map(item => {
                  // get the sectional data
                  // map all the criteria input legends and values
                  const { section } = item
                  return (
                    <div
                      className="bf-verify-criteria-section"
                      key={`bf-section-${section.heading}`}
                    >
                      <Heading
                        className="bf-verify-criteria-section-heading"
                        headingLevel={2}
                      >
                        {section.heading}
                      </Heading>
                      <div>
                        {section.fieldsets.map((item, i) => {
                          // if no value then return generic message
                          return apiCalls.GET.SelectedValue(item) ? (
                            <Items
                              item={item}
                              index={i}
                              key={`bf-criteria-item-${i}`}
                            />
                          ) : (
                            <NoInputGiven
                              item={item}
                              index={i}
                              key={`bf-criteria-item-${i}`}
                            />
                          )
                        })}
                      </div>
                    </div>
                  )
                })}
            </div>
            <div className="bf-section-nav-btn-group">
              <Button outline onClick={() => navigate(-1)}>
                {buttonGroup[0].value}
              </Button>
              <Button
                secondary
                onClick={() =>
                  navigate(
                    `/${ROUTES.indexPath}/${ROUTES.resultsPaths.resultsPath}`
                  )
                }
              >
                {buttonGroup[1].value}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

VerifySelectionsView.propTypes = {
  ui: PropTypes.object,
  data: PropTypes.array,
}

export default VerifySelectionsView
