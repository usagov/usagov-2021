/// <reference types="Cypress" />

import * as utils from '../../support/utils.js'
import * as EN_LOCALE_DATA from '../../../src/shared/locales/en/en.json'
import * as EN_DOLO_MOCK_DATA from '../../../src/shared/api/mock-data/current.json'

const dateOfBirth = utils.getDateByOffset(-(18 * 365.2425 - 1))
const dateOfDeath = utils.getDateByOffset(-30)

const relationship =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[1].fieldset.inputs[0].inputCriteria.values[1].value
const maritalStatus =
  EN_DOLO_MOCK_DATA.data.lifeEventForm.sectionsEligibilityCriteria[0].section
    .fieldsets[2].fieldset.inputs[0].inputCriteria.values[1].value

describe('Validate scrolling when modal is open', () => {
  it('Should disable body from scrolling when model is open', () => {
    cy.visit(utils.storybookUri)

    cy.navigateToModal({
      dateOfBirth,
      relationship,
      maritalStatus,
      dateOfDeath,
    })

    // close when clicked off modal
    cy.get('.ReactModal__Overlay').click('topRight')

    // confirm type works for body
    cy.get('body').type(
      '{upArrow}{upArrow}{downArrow}{downArrow}{leftArrow}{rightArrow}{leftArrow}{rightArrow}ba'
    ) // these types run successfully but do not trigger movement in the window

    // open modal
    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)

    // confirm type works for modal
    cy.get('#benefit-finder-modal').type(
      '{upArrow}{upArrow}{downArrow}{downArrow}{leftArrow}{rightArrow}{leftArrow}{rightArrow}ba'
    )

    let scrollYPosition

    cy.window().then($w => {
      scrollYPosition = $w.scrollY
    })

    cy.window().then($w => {
      expect($w.scrollY).to.be.at.most(scrollYPosition)
    })

    cy.get('#benefit-finder-modal').type('{downArrow}') // modal has an tabIndex

    // confirm that no changes are made
    cy.window().then($w => {
      expect($w.scrollY).to.be.at.most(scrollYPosition)
    })

    cy.get('#benefit-finder-modal').type('{upArrow}')

    // confirm that no changes are made
    cy.window().then($w => {
      expect($w.scrollY).to.be.at.most(scrollYPosition)
    })

    // close modal to confirm that type on modal works
    cy.get('#benefit-finder-modal').type('{esc}')

    // scroll to bottom
    cy.scrollTo('bottom')

    // confirm we are at bottom
    cy.window().then($w => {
      expect($w.scrollY).to.be.greaterThan(scrollYPosition)
    })

    cy.clickButton(EN_LOCALE_DATA.buttonGroup[1].value)

    // confirm we are back at the top
    cy.window().then($w => {
      expect($w.scrollY).to.be.at.most(scrollYPosition)
    })
  })
})
