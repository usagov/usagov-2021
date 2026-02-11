import * as utils from '../../support/utils'
import * as BENEFITS_ELIGIBILITY_DATA from '../../fixtures/benefits-eligibility.json'
import { pageObjects } from '../../support/pageObjects'

beforeEach(() => {
  const selectedData = BENEFITS_ELIGIBILITY_DATA.scenario_1_covid.en.param
  const scenario = utils.encodeURIFromObject(selectedData)
  cy.visit(`${utils.storybookUri}${scenario}`)
  pageObjects.accordionHeading().should('exist')
})

describe('BenefitAccordionGroup component tests', () => {
  it('Validate all accordions are closed on load', () => {
    cy.get('.bf-usa-accordion:visible').each(accordion => {
      cy.wrap(accordion)
        .find('.bf-usa-accordion__button')
        .should('have.attr', 'aria-expanded', 'false')
    })
  })

  it('Validate opening individual accordion expands the clicked accordion and clicking it again closes it', () => {
    // get the first visible accordion and check if it is expanded
    cy.get('.bf-usa-accordion:visible .bf-usa-accordion__button')
      .eq(0)
      .should('have.attr', 'aria-expanded', 'false')
    // get the first visible accordion and click it
    cy.get('.bf-usa-accordion:visible .bf-usa-accordion__button').eq(0).click()
    // get the first visible accordion and check if it is expanded
    cy.get('.bf-usa-accordion:visible .bf-usa-accordion__button')
      .eq(0)
      .should('have.attr', 'aria-expanded', 'true')
    // get the second visible accordion and check if it is expanded
    cy.get('.bf-usa-accordion:visible .bf-usa-accordion__button')
      .eq(1)
      .should('have.attr', 'aria-expanded', 'false')
    // get the first visible accordion and click it
    cy.get('.bf-usa-accordion:visible .bf-usa-accordion__button').eq(0).click()
    // get the first visible accordion and check if it is expanded
    cy.get('.bf-usa-accordion:visible .bf-usa-accordion__button')
      .eq(0)
      .should('have.attr', 'aria-expanded', 'false')
  })

  it('Validate clicking Expand all opens all accordions', () => {
    cy.get('.bf-expand-all').click()
    cy.get('.bf-expand-all').should('contain.text', 'Close all')
    cy.get('.bf-usa-accordion:visible').each(accordion => {
      cy.wrap(accordion)
        .find('.bf-usa-accordion__button')
        .should('have.attr', 'aria-expanded', 'true')
    })
  })

  it('Validate clicking Collapse all closes all accordions', () => {
    cy.get('.bf-expand-all').click()
    cy.get('.bf-expand-all').click()
    cy.get('.bf-expand-all').should('contain.text', 'Expand all')
    cy.get('.bf-usa-accordion:visible').each(accordion => {
      cy.wrap(accordion)
        .find('.bf-usa-accordion__button')
        .should('have.attr', 'aria-expanded', 'false')
    })
  })
})
