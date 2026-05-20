import PropTypes from 'prop-types'
import { Heading } from '@components'
import { createMarkup } from '@utils'
import './_index.scss'

/**
 * a functional component to create a heading block
 * @function
 * @param {string} heading - The inherited heading value
 * @param {string} description - set as html
 * @return {html} returns a semantic html label
 */
const Banner = ({ heading, description }) => {
  return (
    <div className="bf-banner">
      <div className="bf-grid-container grid-container">
        <Heading className="bf-banner-heading" headingLevel={1}>
          {heading}
        </Heading>
        <div
          className="bf-banner-description"
          dangerouslySetInnerHTML={createMarkup(description)}
        />
      </div>
    </div>
  )
}

Banner.propTypes = {
  heading: PropTypes.string,
  description: PropTypes.string,
}

export default Banner
