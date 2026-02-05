import { pageObjects } from './pageObjects'

export function filterDataLayerByEvent(eventName) {
  return cy.window().then(window => {
    return window.dataLayer.filter(item => item.event === eventName)
  })
}

export function getDateByOffset(offset) {
  const date = new Date(Date.now())
  const n = Number(offset)

  date.setDate(date.getDate() + n)
  const month = ('0' + (date.getMonth() + 1)).slice(-2)
  const monthName = date.toLocaleString('default', { month: 'long' })
  const day = ('0' + date.getDate()).slice(-2)
  const year = date.getFullYear()

  return { month: month + ' - ' + monthName, day, year }
}

// encoder utility
export const encodeURIFromObject = obj => {
  return Object.entries(obj)
    .map(
      ([key, value]) =>
        `${encodeURIComponent(key)}=${
          typeof value === 'object'
            ? encodeURIComponent(JSON.stringify(value)) // handles date objects
            : encodeURIComponent(value)
        }`
    )
    .join('&')
}

export const removeID = event => {
  if (Array.isArray(event)) {
    event.forEach(e => delete e['gtm.uniqueEventId'])
  } else {
    delete event['gtm.uniqueEventId']
  }
}

// can be used by all the test that are visiting in story mode
export const storybookUri = `/iframe.html?args=&id=app--primary&viewMode=story&`

export function toggleExpandAllAccordions(
  openAllAccordionsEvent,
  accordionTitle,
  accordionOpenEvent
) {
  pageObjects
    .expandAll()
    .click()
    .then(() => {
      validateEventInDataLayer(
        openAllAccordionsEvent,
        'Open all accordions event'
      )

      pageObjects
        .expandAll()
        .click()
        .then(() => {
          const updatedEvent = {
            ...openAllAccordionsEvent,
            bfData: {
              ...openAllAccordionsEvent.bfData,
              accordionsOpen: !openAllAccordionsEvent.bfData.accordionsOpen,
            },
          }

          validateEventInDataLayer(updatedEvent, 'Toggle open/close accordions')

          pageObjects.accordionByTitle(accordionTitle).click()
          validateEventInDataLayer(accordionOpenEvent, 'Accordion open event')
        })
    })
}

export function validateDataLayerExists() {
  cy.window().then(window => {
    assert.isDefined(window.dataLayer, 'window.dataLayer is defined')
  })
}

export function validateEventData(expectedEvent, index = -1) {
  cy.window().then(window => {
    const event = { ...window.dataLayer.at(index) }
    removeID(event) // Normalize the event
    expect(event).to.deep.equal(expectedEvent)
  })
}

export function validateEventExists(eventName) {
  cy.window().then(window => {
    cy.wrap(window.dataLayer).should(dataLayer => {
      const event = dataLayer.find(e => e.event === eventName)
      assert.isDefined(event, `${eventName} is present`)
    })
  })
}

export function validateEventInDataLayer(expectedEvent, validationDescription) {
  cy.window().then(window => {
    const matchingEvents = window.dataLayer.filter(
      event => event?.event === expectedEvent.event
    )

    assert.isNotEmpty(
      matchingEvents,
      `${validationDescription} event is triggered`
    )

    const normalizedEvent = { ...matchingEvents[matchingEvents.length - 1] }
    removeID(normalizedEvent)

    expect(normalizedEvent).to.deep.equal(expectedEvent)
  })
}

export function validateFormStep(expectedEvent, index = -1) {
  cy.window().then(window => {
    const event = { ...window.dataLayer.at(index) }
    removeID(event)

    expect(event).to.deep.equal(expectedEvent)
  })
}

export function validateGTMIsLoaded() {
  cy.window().then(window => {
    assert.isDefined(
      window.dataLayer.find(event => event.event === 'gtm.js'),
      'GTM is loaded'
    )
  })
}
