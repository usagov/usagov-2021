describe('Home Page [ENG]', () => {
  beforeEach(() => {
      // Set base URL
      cy.visit('/')

      cy.injectAxe()
  })
  it('BTE 7: Banner area/image appears with Welcome text box', () => {
      cy.get('.banner-div')
          .should('be.visible')
          .should('have.css', 'background-image')

      cy.get('.welcome-box')
          .should('be.visible')
  })
  it('BTE 8: How do I area appears correctly with links to four pages/topics', () => {
      cy.get('.how-box')
          .contains('How do I')
          .should('be.visible')

      // Verify there are 4 links
      cy.get('.how-box')
          .find('a')
          .as('links')
          .should('be.visible')
          .should('have.length', 4)

      // Check each link is valid
      cy.get('@links')
          .each((link) => {
              cy.visit(link.attr('href'))
              cy.contains('Page not found').should('not.exist')

              cy.go('back')
          })
  })
  it('BTE 9: Jump to All topics and services link/button appears and jumps to correct place on page', () => {
      // Check text and button
      cy.get('.jump')
          .contains('Jump to')
          .should('be.visible')

      cy.get('.jump')
          .find('img')
          .should('be.visible')
          .should('have.attr', 'src', '/themes/custom/usagov/images/Reimagined_Jump_to_Arrow.svg')
          .should('have.attr', 'alt', 'Jump to all topics and services')

      // Verify link is valid
      cy.get('.jump')
          .each((el) => {
              cy.visit(el.find('a').attr('href'))
              cy.url().should('include', '#all-topics-header')

              cy.visit('/')
          })
  })
  it('BTE 10: Life experiences carousel appears; can navigate through it to see all content (both arrows and circle indicator); can click cards and go to appropriate topic', () => {
      const num_events = 6
      const num_visible = 3

      // Verify correct number of total card slides
      cy.get('.life-events-carousel')
          .find('.slide')
          .should('have.length', num_events)
          .should('be.visible')

      // First 3 slides are visible
      cy.get('.life-events-carousel')
          .find('.slide')
          .first()
          .as('first-slide')
          .should('not.have.attr', 'aria-hidden')
      cy.get('@first-slide')
          .next()
          .should('not.have.attr', 'aria-hidden')
      cy.get('@first-slide')
          .next()
          .next()
          .should('not.have.attr', 'aria-hidden')

      // Check links and imgs in visible slides
      cy.get('.life-events-carousel')
          .find('.slide')
          .not('[aria-hidden="true"]')
          .each((el) => {
              // Current card link is valid
              cy.wrap(el).find('a')
                  .invoke('attr', 'href')
                  .then(href => {
                      cy.request(href)
                          .its('status')
                          .should('eq', 200)
                  })

              // Current card img is valid
              cy.wrap(el).find('img')
                  .should('have.attr', 'src')
              cy.wrap(el).find('img')
                  .should('have.attr', 'alt')
              cy.wrap(el).find('img')
                  .should('be.visible')
                  .should((img) => {
                      // Image loads
                      expect(img[0].naturalWidth).to.be.greaterThan(0)
                      expect(img[0].naturalHeight).to.be.greaterThan(0)
                  })
          })

      // Verify correct number of hidden card slides
      cy.get('.life-events-carousel')
          .find('.slide')
          .filter('[aria-hidden="true"]')
          .as('hidden-slides')
          .should('have.length', num_events - num_visible)

      /*
       * Testing arrow buttons
       */

      // Click through slides using arrow buttons
      cy.get('@hidden-slides')
          .each((el, i) => {
              // Click next button
              cy.get('.life-events-carousel')
                  .find('.next')
                  .click()

              // Verify this slide is now visible
              cy.wrap(el)
                  .should('not.have.attr', 'aria-hidden')

              // Verify the 2 previous slides are visible
              cy.wrap(el).prev()
                  .should('not.have.attr', 'aria-hidden')
              cy.wrap(el).prev().prev()
                  .should('not.have.attr', 'aria-hidden')

              // Verify correct number of hidden card slides
              cy.get('.life-events-carousel')
                  .find('.slide')
                  .filter('[aria-hidden="true"]')
                  .should('have.length', num_events - num_visible)

              // Current card link is valid
              cy.wrap(el).find('a')
                  .invoke('attr', 'href')
                  .then(href => {
                      cy.request(href)
                          .its('status')
                          .should('eq', 200)
                  })
          })

      // Click prev button back to the front
      for (let i = 0; i < num_events - num_visible; i++) {
          cy.get('.life-events-carousel')
              .find('.previous')
              .click()
      }

      // First 3 slides are visible
      cy.get('@first-slide')
          .should('not.have.attr', 'aria-hidden')
      cy.get('@first-slide')
          .next()
          .should('not.have.attr', 'aria-hidden')
      cy.get('@first-slide')
          .next()
          .next()
          .should('not.have.attr', 'aria-hidden')

      // Verify correct number of hidden card slides
      cy.get('.life-events-carousel')
          .find('.slide')
          .filter('[aria-hidden="true"]')
          .should('have.length', num_events - num_visible)

      /*
       * Testing nav circles
       */

      // Click last nav circle
      cy.get('.carousel__navigation_button')
          .last()
          .click()

      // Last 3 slides are visible
      cy.get('.life-events-carousel')
          .find('.slide')
          .last()
          .as('last-slide')
          .should('not.have.attr', 'aria-hidden')
      cy.get('@last-slide')
          .prev()
          .should('not.have.attr', 'aria-hidden')
      cy.get('@last-slide')
          .prev()
          .prev()
          .should('not.have.attr', 'aria-hidden')

      // Click first nav circle
      cy.get('.carousel__navigation_button')
          .first()
          .click()

      // First 3 slides are visible
      cy.get('@first-slide')
          .should('not.have.attr', 'aria-hidden')
      cy.get('@first-slide')
          .next()
          .should('not.have.attr', 'aria-hidden')
      cy.get('@first-slide')
          .next()
          .next()
          .should('not.have.attr', 'aria-hidden')

      // Verify correct number of hidden card slides
      cy.get('.life-events-carousel')
          .find('.slide')
          .filter('[aria-hidden="true"]')
          .should('have.length', num_events - num_visible)
  })
  it('BTE 11: Cards under "All topics and services" appear correctly (icon, title, text, hover state) and are clickable', () => {
      cy.get('.all-topics-background')
          .find('.homepage-card')
          .each((el) => {
              // Validate link
              cy.wrap(el).find('a')
                  .invoke('attr', 'href')
                  .then(href => {
                      cy.request(href)
                          .its('status')
                          .should('eq', 200)
                  })

              // Verify number of children
              cy.wrap(el).find('a')
                  .children()
                  .should('have.length', 3)

              // Css check for hover state
              cy.wrap(el)
                  .realHover()
                  .should('have.css', 'background-color', 'rgb(204, 236, 242)')
          })
  })
})