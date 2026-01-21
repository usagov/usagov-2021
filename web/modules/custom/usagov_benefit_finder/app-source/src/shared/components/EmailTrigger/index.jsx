import { useState, useContext } from 'react'
import { RouteContext } from '@/App'
import { buildURIParameter } from '@utils'

/**
 * a functional component that renders a anchor with mailto email context
 * @component
 * @return {html} returns a semantic html anchor element with a custom function onClick event
 */
const EmailTrigger = ({ ui, data }) => {
  const ROUTES = useContext(RouteContext)
  /**
   * a state hook that contains the window location href
   * @return {string} current state of window location href
   */
  const [shareLink, setShareLink] = useState(() =>
    buildURIParameter(
      `${window.location.origin}${ROUTES.basePath}${ROUTES.indexPath}`,
      data
    )
  )

  const emailBody = `${encodeURIComponent(shareLink)}`

  /**
   * a handler that triggers an email with context from the users window location href
   */
  const handleClick = e => {
    e.preventDefault()
    setShareLink(
      buildURIParameter(
        `${window.location.origin}${ROUTES.basePath}${ROUTES.indexPath}`,
        data
      )
    )
    window.location = `mailto:?subject=${ui?.emailSubject}&body=${emailBody}`
  }

  return (
    <a
      tabIndex={0}
      role="link"
      className="bf-email-trigger bf-usa-link usa-link"
      onClick={e => handleClick(e)}
      onKeyDown={e => handleClick(e)}
    >
      {ui?.emailTrigger || 'Email'}
    </a>
  )
}

export default EmailTrigger
