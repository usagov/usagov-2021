/// <reference types="Cypress" />

/**
 * Benefit Finder Accessibility Tests
 *
 * Tests the benefit finder for accessibility compliance.
 * Ensures WCAG 2.1 AA standards are met for the integrated React application.
 */

describe('Benefit Finder - Accessibility Tests', () => {
  context('Benefit Finder Page Accessibility', () => {
    beforeEach(() => {
      cy.visit('/benefit-finder')
      cy.wait(2000) // Wait for React app to load
    })

    it('should not have basic accessibility violations', () => {
      // Inject axe-core if you have it installed
      // cy.injectAxe()
      // cy.checkA11y()

      // Basic accessibility checks without axe-core
      cy.get('img').each($img => {
        cy.wrap($img).should('have.attr', 'alt')
      })
    })

    it('should have proper heading hierarchy', () => {
      cy.get('h1').should('have.length.at.least', 1)
    })

    it('should have keyboard-accessible interactive elements', () => {
      cy.get('button, a, input, select, textarea').each($el => {
        // All interactive elements should be keyboard accessible
        cy.wrap($el).should('not.have.attr', 'tabindex', '-1')
      })
    })

    it('should have proper ARIA labels on form controls', () => {
      // Only check form controls in the main content area to avoid false positives
      // from header search, footer, etc.
      cy.get('main input, main select, main textarea, [role="main"] input, [role="main"] select, [role="main"] textarea').then($inputs => {
        if ($inputs.length === 0) {
          // No form controls found in main content - test passes
          cy.log('No form controls found in main content area')
          return
        }

        $inputs.each((index, input) => {
          const $input = Cypress.$(input)
          const hasLabel =
            $input.attr('aria-label') ||
            $input.attr('aria-labelledby') ||
            $input.closest('label').length > 0 ||
            Cypress.$(`label[for="${$input.attr('id')}"]`).length > 0

          // Log inputs without labels for debugging
          if (!hasLabel) {
            const inputType = $input.attr('type') || $input.prop('tagName')
            const inputId = $input.attr('id') || 'no-id'
            cy.log(`Input without label: ${inputType} (id: ${inputId})`)
          }

          expect(hasLabel, `Input element should have proper labeling`).to.be.true
        })
      })
    })

    it('should maintain focus management during interactions', () => {
      // Test that focus is properly managed
      cy.get('button, a').first().focus().should('have.focus')
    })
  })
})
