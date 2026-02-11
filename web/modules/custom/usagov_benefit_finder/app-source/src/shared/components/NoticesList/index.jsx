import PropTypes from 'prop-types'
import { createMarkup } from '@utils'
import { Icon } from '@components'

import './_index.scss'

/**
 * a functional component that renders a list of information
 * @component
 * @param {array} data inherited ui translations
 * @return {html} returns a semantic ul
 */
const NoticesList = ({ data, iconAlt }) => {
  /**
   * a functional component that renders a link as a button
   * @component
   * @param {object} data inherited ui translations
   * @return {html} returns a semantic li
   */
  const Notices = data => {
    return (
      data &&
      data.data.map((item, i) => {
        return (
          <li className="bf-notice" key={`notice-${i}`}>
            <Icon type="info" aria-label={iconAlt} role="img" />
            <div
              className="bf-notice-item"
              dangerouslySetInnerHTML={createMarkup(item.notice)}
            ></div>
          </li>
        )
      })
    )
  }

  return (
    <div className="bf-notices">
      <ul className="bf-notices-list add-list-reset">
        <Notices data={data} />
      </ul>
    </div>
  )
}

NoticesList.propTypes = {
  data: PropTypes.array,
}

export default NoticesList
