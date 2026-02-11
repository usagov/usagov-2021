import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import { RouteContext } from '@/App'
import { cleanString } from '@utils'
import LifeEventSection from '../index.jsx'
import content from '@api/mock-data/current.js'
import * as en from '@locales/en/en.json'

const { data } = JSON.parse(content)
const { lifeEventForm } = data
const ROUTES = {
  indexPath: 'death',
  formPaths: lifeEventForm.sectionsEligibilityCriteria.map(item => {
    const title = cleanString(item.section.heading)
    return title
  }),
}

beforeAll(() => {
  const mockLocation = {
    pathname: `${`/${ROUTES.indexPath}/${ROUTES.formPaths[0]}`}`,
  }
  const noop = () => {}
  Object.defineProperty(window, 'scrollTo', { value: noop, writable: true })
  Object.defineProperty(window, 'location', { value: mockLocation })
})

describe('LifeEventSection', () => {
  it('renders a match to the previous snapshot', async () => {
    const { asFragment } = render(
      <RouteContext.Provider value={ROUTES}>
        <BrowserRouter>
          <LifeEventSection
            data={lifeEventForm.sectionsEligibilityCriteria}
            ui={en}
          />
        </BrowserRouter>
      </RouteContext.Provider>
    )
    screen.getByTestId('bf-section')
    expect(asFragment()).toMatchSnapshot()
  })
})
