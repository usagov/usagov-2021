import PropTypes from 'prop-types'
import { Summary, Heading } from '@components'
import { createMarkup } from '@utils'
import './_index.scss'

const EligibleBenefitsHeadingBlock = ({ ui }) => {
  const { eligible, summaryBox } = ui
  return (
    <div>
      <Heading className="bf-eligible-view-heading" headingLevel={2}>
        {eligible?.heading}
      </Heading>
      <Heading
        className="bf-eligible-view-description"
        headingLevel={3}
        dangerouslySetInnerHTML={createMarkup(eligible?.description)}
      />
      <Summary
        heading={summaryBox?.heading}
        listItems={summaryBox?.list}
        cta={summaryBox?.cta}
      />
    </div>
  )
}

EligibleBenefitsHeadingBlock.propTypes = {
  props: PropTypes.any,
}

export default EligibleBenefitsHeadingBlock
