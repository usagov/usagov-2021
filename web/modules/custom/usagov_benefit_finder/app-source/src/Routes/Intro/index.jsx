import { useEffect, useContext } from 'react'
import { useNavigate, useLocation } from 'react-router'
import { RouteContext } from '@/App'
import { dataLayerUtils, a11yTitles } from '@utils'
import { useScrollToAnchor } from '@hooks'
import PropTypes from 'prop-types'
import {
  Button,
  Banner,
  Heading,
  NoticesList,
  ProcessList,
  TimeIndicator,
} from '@components'

import './_index.scss'

/**
 * a compound component that renders the introduction start of the form process
 * @component
 * @param {object} content - inherited life event content
 * @param {object} ui - life event form ui translations
 * @return {html} returns information page view if data exist
 */
const Intro = ({ content, ui, hasQueryParams }) => {
  const { timeEstimate, title, summary } = content
  const { heading, timeIndicator, steps, notices, button } = ui
  const { intro } = dataLayerUtils.dataLayerStructure
  const ROUTES = useContext(RouteContext)
  const navigate = useNavigate()
  const location = useLocation()
  useScrollToAnchor(location)

  const handleStep = () => {
    navigate(`/${ROUTES.indexPath}/${ROUTES.formPaths[0]}`)
  }

  // if we have query parameters direct user to the results page
  useEffect(() => {
    hasQueryParams &&
      navigate(
        `/${ROUTES.indexPath}/${ROUTES.resultsPaths.resultsPath}${location.search}`
      )
  }, [hasQueryParams])

  // handle dataLayer
  useEffect(() => {
    // gtm
    !hasQueryParams &&
      dataLayerUtils.dataLayerPush(window, {
        event: intro.event,
        bfData: { pageView: intro.bfData.pageView, viewTitle: title },
      })
    a11yTitles(title)
  }, [hasQueryParams])

  return (
    content && (
      <div className="bf-intro">
        <Banner heading={title} description={summary} />
        <div className="bf-grid-container grid-container">
          <div className="bf-intro-process-group">
            <div className="bf-intro-process-list">
              <div className="bf-intro-process-heading">
                <Heading headingLevel={2}>{heading}</Heading>
                <TimeIndicator
                  description={timeIndicator}
                  timeEstimate={timeEstimate}
                />
              </div>
              <ProcessList steps={steps.list} description={steps.title} />
            </div>
            <div className="bf-line-separator-wrapper--vertical">
              <div className="bf-line-separator--vertical" />
            </div>
            <div className="bf-intro-process-notices">
              <Heading
                className="bf-intro-process-notices-heading"
                headingLevel={2}
              >
                {notices.heading}
              </Heading>
              <NoticesList
                className="bf-intro-process-notices-list"
                data={notices.list}
                iconAlt={notices.iconAlt}
              />
            </div>
          </div>
          <div className="bf-cta-wrapper">
            <Button secondary onClick={() => handleStep()} data-test="button">
              {button}
            </Button>
          </div>
        </div>
      </div>
    )
  )
}

Intro.propTypes = {
  data: PropTypes.object,
  ui: PropTypes.object,
  step: PropTypes.number,
}

export default Intro
