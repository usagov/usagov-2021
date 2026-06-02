import * as utils from '../../support/utils'
import { pageObjects } from '../../support/pageObjects'
import * as BENEFITS_ELIGIBILITY_DATA from '../../fixtures/benefits-eligibility.json'

const accordionButtonSelector =
  '.bf-usa-accordion__button.usa-accordion__button'

const assertAccordionsExpanded = expectedState => {
  cy.get(accordionButtonSelector).should($accordions => {
    expect($accordions.length).to.be.greaterThan(0)

    $accordions.each((index, accordion) => {
      expect(accordion.getAttribute('aria-expanded')).to.equal(expectedState)
    })
  })
}

beforeEach(() => {
  const selectedData = BENEFITS_ELIGIBILITY_DATA.scenario_1_covid.en.param
  const scenario = utils.encodeURIFromObject(selectedData)
  cy.visit(`${utils.storybookUri}${scenario}`)
  pageObjects.accordionHeading().should('exist')
})

describe('open all interaction tests', () => {
  it('Validate clicking open all expands the accordions', () => {
    pageObjects
      .expandAll()
      .click()
      .then(() => {
        assertAccordionsExpanded('true')
      })
  })

  it('Validate clicking close all closes all the accordions', () => {
    pageObjects
      .expandAll()
      .click()
      .then(() => {
        pageObjects
          .expandAll()
          .click()
          .then(() => {
            assertAccordionsExpanded('false')
          })
      })
  })

  it('Validate clicking open all expands the accordions, then toggling eligible view, sets them back to close', () => {
    pageObjects
      .expandAll()
      .click()
      .then(() => {
        assertAccordionsExpanded('true')

        pageObjects
          .notEligibleResultsButton()
          .click()
          .then(() => {
            assertAccordionsExpanded('false')
          })
      })
  })

  it('from the not eligible view, validate clicking open all expands the accordions, then toggling eligible view, sets them back to close', () => {
    pageObjects
      .notEligibleResultsButton()
      .click()
      .then(() => {
        assertAccordionsExpanded('false')

        // click expand all and make sure they are now open
        pageObjects
          .expandAll()
          .click()
          .then(() => {
            assertAccordionsExpanded('true')

            // click step back and they should all be closed again
            cy.go('back').then(() => {
              assertAccordionsExpanded('false')
            })
          })
      })
  })
})
