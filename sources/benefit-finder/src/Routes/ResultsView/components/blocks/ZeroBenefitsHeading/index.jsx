import PropTypes from 'prop-types'
import { useContext } from 'react'
import { RouteContext } from '@/App'
import { useNavigate } from 'react-router'
import { Button, Heading, StepBackButton } from '@components'
import { createMarkup } from '@utils'

import './_index.scss'

const ZeroBenefitsHeadingBlock = ({
  handleViewToggle,
  notEligibleView,
  ui,
}) => {
  const ROUTES = useContext(RouteContext)
  const navigate = useNavigate()
  return (
    <>
      <Heading
        className="bf-zero-benefits-view-heading"
        data-testid="zero-benefits-view-heading"
        headingLevel={2}
      >
        {ui?.heading}
      </Heading>
      <Heading
        className="bf-zero-benefits-view-description"
        headingLevel={3}
        dangerouslySetInnerHTML={createMarkup(ui?.description)}
      />
      <div className="bf-back-to-form-cta">
        <StepBackButton
          onClick={() =>
            navigate(`/${ROUTES.indexPath}/${ROUTES.formPaths[0]}`)
          }
        >
          {ui?.backcta.link}
        </StepBackButton>
      </div>
      {!notEligibleView && (
        <div className="bf-zero-benefits-view-cta">
          <Button
            data-testid="zero-benefits-view-cta-button"
            onClick={handleViewToggle}
            secondary
          >
            {ui?.cta}
          </Button>
        </div>
      )}
    </>
  )
}

ZeroBenefitsHeadingBlock.propTypes = {
  handleViewToggle: PropTypes.func,
  notEligibleView: PropTypes.bool,
  ui: PropTypes.object,
}

export default ZeroBenefitsHeadingBlock
