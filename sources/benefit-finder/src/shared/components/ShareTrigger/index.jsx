import { useState, useContext } from 'react'
import { RouteContext } from '@/App'
import { buildURIParameter } from '@utils'

/**
 * a functional component that renders a button with copy-to-clipboard functionality
 * @component
 * @return {html} returns a semantic html button element with a custom function onClick event
 */

const ShareTrigger = ({ ui, data }) => {
  const ROUTES = useContext(RouteContext)
  /**
   * a state hook that contains the window location href
   * @return {string} current state of window location href
   */
  const [shareLink, setShareLink] = useState(() =>
    buildURIParameter(
      `${window.location.origin}${ROUTES.basePath}/${ROUTES.indexPath}`,
      data
    )
  )

  /**
   * a handler that copies the current window location href to the users clipboard
   */
  const handleClick = e => {
    e.preventDefault()
    setShareLink(
      buildURIParameter(
        `${window.location.origin}${ROUTES.basePath}/${ROUTES.indexPath}`,
        data
      )
    )
    navigator.clipboard.writeText(shareLink).then(
      () => alert(`${ui?.shareLinkContent} ${shareLink}`),
      err => alert('Failed to copy', err)
    )
  }

  return (
    <a
      tabIndex={0}
      role="link"
      className="bf-share-trigger bf-usa-link usa-link"
      onClick={e => handleClick(e)}
      onKeyDown={e => handleClick(e)}
      data-testid="bf-share-trigger"
    >
      {ui?.shareTrigger || 'Share'}
    </a>
  )
}

export default ShareTrigger
