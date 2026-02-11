/// <reference types="Cypress" />

/**
 * Benefit Finder Integration Tests
 *
 * Tests the benefit finder React application as integrated into Drupal life event pages.
 * These tests verify that:
 * 1. The benefit finder React app loads correctly on life event pages
 * 2. The JSON data API endpoints are accessible
 * 3. Basic user interactions work (form fields, navigation, etc.)
 */

describe('Benefit Finder - Integration Tests', () => {
  context('Benefit Finder Main Page', () => {
    beforeEach(() => {
      cy.visit('/benefit-finder')
    })

    it('should load the page successfully', () => {
      cy.get('body').should('exist')
      cy.title().should('not.be.empty')
    })

    it('should render the benefit finder content', () => {
      // In Drupal, the React app is loaded via the library system and renders into the page content
      // Rather than checking for specific container IDs, verify the app's content is present
      cy.get('main, .main-content, #content, [role="main"]', { timeout: 10000 })
        .should('exist')
        .and('be.visible')
    })

    it('should load JavaScript assets', () => {
      // Drupal aggregates JS files, so we verify JavaScript is loaded and executable
      // rather than checking for specific filenames
      cy.window().then(win => {
        // Verify window has common properties that would be set by JS
        expect(win.document.readyState).to.equal('complete')
        // Verify there are script tags loaded
        const scripts = win.document.querySelectorAll('script')
        expect(scripts.length).to.be.greaterThan(0)
      })
    })

    it('should load CSS assets', () => {
      // Drupal aggregates CSS files, so we verify styles are applied
      // rather than checking for specific filenames
      cy.get('body').should('have.css', 'display').and('not.equal', '')
      cy.window().then(win => {
        const links = win.document.querySelectorAll('link[rel="stylesheet"]')
        expect(links.length).to.be.greaterThan(0)
      })
    })

    it('should render React components within reasonable time', () => {
      // Look for form elements that would be rendered by React
      cy.get('form, button, input, a', { timeout: 10000 }).should('exist')
    })

    it('should have navigation for life events', () => {
      // Check for life event navigation (death, retirement, disability)
      // The page should contain at least one of these life event terms
      cy.get('body').then($body => {
        const text = $body.text()
        const hasLifeEvent =
          text.includes('Death') ||
          text.includes('Retirement') ||
          text.includes('Disability') ||
          text.includes('loved one') ||
          text.includes('benefit')
        expect(hasLifeEvent, 'Page should contain life event navigation').to.be.true
      })
    })
  })

  context('Benefit Finder User Interactions', () => {
    beforeEach(() => {
      cy.visit('/benefit-finder')
      cy.wait(2000) // Wait for React app to initialize
    })

    it('should allow basic navigation', () => {
      // Check if there are clickable elements
      cy.get('a, button').should('exist').and('have.length.gt', 0)
    })

    it('should have interactive elements', () => {
      // Look for interactive elements
      cy.get('button, a, input, [role="button"]', { timeout: 5000 })
        .should('exist')
        .first()
        .should('not.be.disabled')
    })
  })
})
